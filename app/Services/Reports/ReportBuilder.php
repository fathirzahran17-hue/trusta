<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Menyusun setiap laporan menjadi "dokumen" seragam:
 *  title, subtitle, filters, columns, rows, summary, notes, orientation.
 * Dokumen yang sama dipakai untuk tampilan layar, cetak/PDF, Excel dan CSV.
 *
 * Tipe baris: section | row | subtotal | total | grand | spacer
 * Format kolom: text | date | money | qty | int
 */
class ReportBuilder
{
    public function __construct(private int $companyId) {}

    /* ------------------------------------------------------------------ helpers */

    private function sums(?string $from, string $to): array
    {
        return JournalDetail::query()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)
            ->where('journals.status', 'posted')
            ->whereDate('journals.transaction_date', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('journals.transaction_date', '>=', $from))
            ->groupBy('journal_details.account_id')
            ->selectRaw('journal_details.account_id as aid, SUM(journal_details.debit) as d, SUM(journal_details.credit) as c')
            ->get()
            ->keyBy('aid')
            ->map(fn ($r) => [(float) $r->d, (float) $r->c])
            ->all();
    }

    private function accounts(string $type)
    {
        return Account::where('company_id', $this->companyId)->where('type', $type)->orderBy('code')->get()
            ->filter(fn ($a) => ! $a->is_header)->values();
    }

    private function period(string $a, string $b): string
    {
        return 'Periode ' . \App\Support\ReportFormat::date($a) . ' s/d ' . \App\Support\ReportFormat::date($b);
    }

    private function dateFilters(string $start, string $end): array
    {
        return [
            ['name' => 'start_date', 'label' => 'Dari', 'type' => 'date', 'value' => $start],
            ['name' => 'end_date', 'label' => 'Sampai', 'type' => 'date', 'value' => $end],
        ];
    }

    private function acctCols(string $amountLabel = 'Jumlah (Rp)'): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'width' => 12],
            ['key' => 'name', 'label' => 'Nama Akun', 'width' => 46],
            ['key' => 'amount', 'label' => $amountLabel, 'width' => 22, 'align' => 'right', 'format' => 'money'],
        ];
    }

    private function sec(string $label): array { return ['type' => 'section', 'label' => $label]; }
    private function row(array $cells, int $indent = 0): array { return ['type' => 'row', 'cells' => $cells, 'indent' => $indent]; }
    private function tot(string $type, string $label, $amount, int $cols = 3): array
    {
        $cells = array_fill(0, $cols, '');
        $cells[1] = $label;
        $cells[$cols - 1] = $amount;
        return ['type' => $type, 'cells' => $cells];
    }

    /* ------------------------------------------------------------ keuangan */

    /** Saldo (positif = sesuai sifat akun) tiap tipe laba rugi pada rentang sums */
    private function plBlock(string $type, array $sums): array
    {
        $creditNormal = in_array($type, ['revenue', 'other_income'], true);
        $rows = []; $total = 0;
        foreach ($this->accounts($type) as $a) {
            [$d, $c] = $sums[$a->id] ?? [0, 0];
            $v = $creditNormal ? $c - $d : $d - $c;
            $total += $v;
            if (abs($v) > 0.004) $rows[] = $this->row([$a->code, $a->name, $v], 1);
        }
        return [$rows, $total];
    }

    /**
     * Laba rugi bertingkat: Pendapatan − HPP = Laba Kotor − Beban Usaha = Laba Usaha ± Lain-lain = Laba Bersih.
     * @return array [rows, pendapatan, hpp, bebanTotal(usaha+lain), net]
     */
    private function plRows(string $start, string $end): array
    {
        $sums = $this->sums($start, $end);
        [$revRows, $rev] = $this->plBlock('revenue', $sums);
        [$cogsRows, $cogs] = $this->plBlock('cost_of_sales', $sums);
        [$expRows, $exp] = $this->plBlock('expense', $sums);
        [$oiRows, $oi] = $this->plBlock('other_income', $sums);
        [$oeRows, $oe] = $this->plBlock('other_expense', $sums);

        $gross = $rev - $cogs;
        $operating = $gross - $exp;
        $net = $operating + $oi - $oe;

        $rows = [$this->sec('PENDAPATAN'), ...$revRows, $this->tot('subtotal', 'Total Pendapatan', $rev), ['type' => 'spacer']];
        $rows = [...$rows, $this->sec('HARGA POKOK PENJUALAN'), ...$cogsRows, $this->tot('subtotal', 'Total Harga Pokok Penjualan', $cogs),
            $this->tot('total', 'LABA KOTOR', $gross), ['type' => 'spacer']];
        $rows = [...$rows, $this->sec('BEBAN USAHA'), ...$expRows, $this->tot('subtotal', 'Total Beban Usaha', $exp),
            $this->tot('total', 'LABA USAHA', $operating), ['type' => 'spacer']];
        if ($oiRows) {
            $rows = [...$rows, $this->sec('PENDAPATAN LAIN-LAIN'), ...$oiRows, $this->tot('subtotal', 'Total Pendapatan Lain-lain', $oi), ['type' => 'spacer']];
        }
        if ($oeRows) {
            $rows = [...$rows, $this->sec('BEBAN LAIN-LAIN'), ...$oeRows, $this->tot('subtotal', 'Total Beban Lain-lain', $oe), ['type' => 'spacer']];
        }
        $rows[] = $this->tot('grand', $net >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH', $net);

        return [$rows, $rev, $cogs, $exp + $oe - $oi, $net];
    }

    public function profitLoss(Request $r): array
    {
        $start = $r->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $end = $r->input('end_date', Carbon::now()->toDateString());
        [$rows, $rev, $cogs, $exp, $net] = $this->plRows($start, $end);

        return [
            'key' => 'profit-loss', 'title' => 'Laporan Laba Rugi', 'subtitle' => $this->period($start, $end),
            'file' => "Laba_Rugi_{$start}_sd_{$end}", 'filters' => $this->dateFilters($start, $end),
            'columns' => $this->acctCols(), 'rows' => $rows, 'indent_col' => 1,
            'summary' => [
                ['label' => 'Total Pendapatan', 'value' => $rev, 'format' => 'money'],
                ['label' => 'Harga Pokok Penjualan', 'value' => $cogs, 'format' => 'money'],
                ['label' => 'Beban (usaha & lain)', 'value' => $exp, 'format' => 'money'],
                ['label' => $net >= 0 ? 'Laba Bersih' : 'Rugi Bersih', 'value' => $net, 'format' => 'money', 'tone' => $net >= 0 ? 'good' : 'bad'],
            ],
        ];
    }

    /** [rows, totalAssets, totalLiabEquity] posisi keuangan per tanggal */
    private function bsRows(string $asOf): array
    {
        $sums = $this->sums(null, $asOf);
        $bal = function ($a, $debitNormal) use ($sums) {
            [$d, $c] = $sums[$a->id] ?? [0, 0];
            return (float) $a->opening_balance + ($debitNormal ? $d - $c : $c - $d);
        };

        $rows = [];
        $block = function (string $label, string $type, bool $debitNormal) use (&$rows, $bal) {
            $rows[] = $this->sec($label);
            $sum = 0;
            foreach ($this->accounts($type) as $a) {
                $v = $bal($a, $debitNormal);
                $sum += $v;
                if (abs($v) > 0.004) $rows[] = $this->row([$a->code, $a->name, $v], 1);
            }
            return $sum;
        };

        $assets = $block('ASET', 'asset', true);
        $rows[] = $this->tot('total', 'TOTAL ASET', $assets);
        $rows[] = ['type' => 'spacer'];

        $liab = $block('KEWAJIBAN', 'liability', false);
        $rows[] = $this->tot('subtotal', 'Total Kewajiban', $liab);
        $rows[] = ['type' => 'spacer'];

        // Ekuitas: Laba Tahun Berjalan & Laba Ditahan terhubung ke laba rugi per tahun fiskal (EarningsLink)
        $rows[] = $this->sec('EKUITAS');
        $link = \App\Services\EarningsLink::split($this->companyId, $asOf);
        [$curId, $retId] = \App\Services\EarningsLink::accountIds($this->companyId);
        $eq = 0;
        foreach ($this->accounts('equity') as $a) {
            $v = $bal($a, false);
            if ($a->id === $curId) $v += $link['current'];
            if ($a->id === $retId) $v += $link['prior'];
            $eq += $v;
            if (abs($v) > 0.004) $rows[] = $this->row([$a->code, $a->name, $v], 1);
        }
        if (!$curId && abs($link['current']) > 0.004) {
            $eq += $link['current'];
            $rows[] = $this->row(['', 'Laba Tahun Berjalan (otomatis)', $link['current']], 1);
        }
        if (!$retId && abs($link['prior']) > 0.004) {
            $eq += $link['prior'];
            $rows[] = $this->row(['', 'Laba Ditahan Tahun Sebelumnya (otomatis)', $link['prior']], 1);
        }
        $rows[] = $this->tot('subtotal', 'Total Ekuitas', $eq);
        $rows[] = ['type' => 'spacer'];
        $liabEq = $liab + $eq;
        $rows[] = $this->tot('total', 'TOTAL KEWAJIBAN + EKUITAS', $liabEq);

        return [$rows, $assets, $liabEq, $liab, $eq];
    }

    public function balanceSheet(Request $r): array
    {
        $asOf = $r->input('as_of_date', Carbon::now()->toDateString());
        [$rows, $assets, $liabEq, $liab, $eq] = $this->bsRows($asOf);
        $diff = round($assets - $liabEq, 2);

        return [
            'key' => 'balance-sheet', 'title' => 'Neraca (Laporan Posisi Keuangan)', 'subtitle' => 'Per ' . \App\Support\ReportFormat::date($asOf),
            'file' => "Neraca_{$asOf}",
            'filters' => [['name' => 'as_of_date', 'label' => 'Per Tanggal', 'type' => 'date', 'value' => $asOf]],
            'columns' => $this->acctCols(), 'rows' => $rows, 'indent_col' => 1,
            'summary' => [
                ['label' => 'Total Aset', 'value' => $assets, 'format' => 'money'],
                ['label' => 'Total Kewajiban', 'value' => $liab, 'format' => 'money'],
                ['label' => 'Total Ekuitas', 'value' => $eq, 'format' => 'money'],
            ],
            'notes' => $diff == 0
                ? ['Neraca seimbang: Aset = Kewajiban + Ekuitas.']
                : ['PERHATIAN: Neraca tidak seimbang, selisih Rp ' . \App\Support\ReportFormat::money($diff) . '. Periksa saldo awal akun dan jurnal.'],
            'notes_tone' => $diff == 0 ? 'good' : 'bad',
        ];
    }

    public function integrated(Request $r): array
    {
        $start = $r->input('start_date', Carbon::now()->startOfYear()->toDateString());
        $end = $r->input('end_date', Carbon::now()->toDateString());
        [$plRows, $rev, $cogs, $exp, $net] = $this->plRows($start, $end);
        [$bsRows, $assets, $liabEq] = $this->bsRows($end);

        // Urutan: Neraca (posisi keuangan) dulu, baru Laba Rugi
        $rows = array_merge(
            [$this->sec('I. NERACA — Per ' . \App\Support\ReportFormat::date($end))], $bsRows,
            [['type' => 'spacer']],
            [$this->sec('II. LAPORAN LABA RUGI — ' . $this->period($start, $end))], $plRows
        );

        return [
            'key' => 'integrated', 'title' => 'Laporan Keuangan Terpadu', 'subtitle' => $this->period($start, $end),
            'file' => "Laporan_Keuangan_Terpadu_{$end}", 'filters' => $this->dateFilters($start, $end),
            'columns' => $this->acctCols(), 'rows' => $rows, 'indent_col' => 1,
            'summary' => [
                ['label' => 'Total Aset', 'value' => $assets, 'format' => 'money'],
                ['label' => 'Total Kewajiban + Ekuitas', 'value' => $liabEq, 'format' => 'money'],
                ['label' => 'Laba/Rugi Bersih', 'value' => $net, 'format' => 'money', 'tone' => $net >= 0 ? 'good' : 'bad'],
            ],
        ];
    }

    /* ---------------------------------------------------------- buku besar */

    public function generalLedger(Request $r): array
    {
        $start = $r->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $end = $r->input('end_date', Carbon::now()->toDateString());
        $accountId = $r->input('account_id');

        $all = Account::where('company_id', $this->companyId)->orderBy('code')->get()->filter(fn ($a) => ! $a->is_header)->values();
        $selected = $accountId ? $all->where('id', (int) $accountId) : $all;

        $showVoid = $r->input('void') === '1';
        $hide = $showVoid ? [] : \App\Support\LedgerPeriod::cancelledPairIds((int) $this->companyId);
        $before = $this->sums(null, Carbon::parse($start)->subDay()->toDateString());
        $details = JournalDetail::query()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)->where('journals.status', 'posted')
            ->whereDate('journals.transaction_date', '>=', $start)->whereDate('journals.transaction_date', '<=', $end)
            ->whereIn('journal_details.account_id', $selected->pluck('id'))
            ->when($hide, fn ($q) => $q->whereNotIn('journals.id', $hide)) // transaksi dibatalkan + jurnal baliknya (saling meniadakan)
            ->orderBy('journals.transaction_date')->orderBy('journals.id')
            ->get(['journal_details.account_id', 'journal_details.debit', 'journal_details.credit', 'journal_details.description as line_desc',
                'journals.transaction_date', 'journals.ref_number', 'journals.description as j_desc'])
            ->groupBy('account_id');

        $rows = [];
        foreach ($selected as $a) {
            $lines = $details->get($a->id, collect());
            [$bd, $bc] = $before[$a->id] ?? [0, 0];
            $debitNormal = in_array($a->type, ['asset', 'expense']);
            $open = (float) $a->opening_balance + ($debitNormal ? $bd - $bc : $bc - $bd);
            if ($lines->isEmpty() && abs($open) < 0.005) continue;

            $rows[] = $this->sec($a->code . ' — ' . $a->name);
            $rows[] = ['type' => 'row', 'cells' => ['', '', 'Saldo Awal', '', '', $open], 'italic' => true];
            $run = $open; $td = 0; $tc = 0;
            foreach ($lines as $l) {
                $run += $debitNormal ? $l->debit - $l->credit : $l->credit - $l->debit;
                $td += $l->debit; $tc += $l->credit;
                $rows[] = $this->row([$l->transaction_date, $l->ref_number, $l->line_desc ?: $l->j_desc, (float) $l->debit, (float) $l->credit, $run]);
            }
            $rows[] = ['type' => 'subtotal', 'cells' => ['', '', 'Total Mutasi / Saldo Akhir', $td, $tc, $run]];
            $rows[] = ['type' => 'spacer'];
        }

        return [
            'key' => 'general-ledger', 'title' => 'Buku Besar', 'subtitle' => $this->period($start, $end),
            'file' => "Buku_Besar_{$start}_sd_{$end}", 'orientation' => 'landscape',
            'filters' => array_merge($this->dateFilters($start, $end), [[
                'name' => 'account_id', 'label' => 'Akun', 'type' => 'select', 'value' => $accountId,
                'options' => ['' => 'Semua akun'] + $all->mapWithKeys(fn ($a) => [$a->id => $a->code . ' — ' . $a->name])->all(),
            ], [
                'name' => 'void', 'label' => 'Transaksi dibatalkan', 'type' => 'select', 'value' => $showVoid ? '1' : '',
                'options' => ['' => 'Sembunyikan', '1' => 'Tampilkan'],
            ]]),
            'columns' => [
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 14, 'format' => 'date'],
                ['key' => 'ref', 'label' => 'No. Referensi', 'width' => 20],
                ['key' => 'desc', 'label' => 'Keterangan', 'width' => 44],
                ['key' => 'debit', 'label' => 'Debit (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
                ['key' => 'credit', 'label' => 'Kredit (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
                ['key' => 'bal', 'label' => 'Saldo (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows, 'empty' => 'Tidak ada transaksi pada periode ini.',
        ];
    }

    public function trialBalance(Request $r): array
    {
        $asOf = $r->input('as_of_date', Carbon::now()->toDateString());
        $sums = $this->sums(null, $asOf);
        $rows = []; $td = 0; $tc = 0;
        foreach (Account::where('company_id', $this->companyId)->orderBy('code')->get()->filter(fn ($a) => ! $a->is_header) as $a) {
            [$d, $c] = $sums[$a->id] ?? [0, 0];
            $debitNormal = in_array($a->type, ['asset', 'expense']);
            $signed = (float) $a->opening_balance * ($debitNormal ? 1 : -1) + $d - $c; // + = debit
            if (abs($signed) < 0.005) continue;
            $debit = $signed > 0 ? $signed : 0;
            $credit = $signed < 0 ? -$signed : 0;
            $td += $debit; $tc += $credit;
            $rows[] = $this->row([$a->code, $a->name, $debit, $credit]);
        }
        $rows[] = ['type' => 'grand', 'cells' => ['', 'TOTAL', $td, $tc]];
        $diff = round($td - $tc, 2);

        return [
            'key' => 'trial-balance', 'title' => 'Neraca Saldo', 'subtitle' => 'Per ' . \App\Support\ReportFormat::date($asOf),
            'file' => "Neraca_Saldo_{$asOf}",
            'filters' => [['name' => 'as_of_date', 'label' => 'Per Tanggal', 'type' => 'date', 'value' => $asOf]],
            'columns' => [
                ['key' => 'code', 'label' => 'Kode', 'width' => 12],
                ['key' => 'name', 'label' => 'Nama Akun', 'width' => 46],
                ['key' => 'debit', 'label' => 'Debit (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'money'],
                ['key' => 'credit', 'label' => 'Kredit (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows,
            'notes' => [$diff == 0 ? 'Seimbang: total debit sama dengan total kredit.' : 'PERHATIAN: tidak seimbang, selisih Rp ' . \App\Support\ReportFormat::money($diff) . '.'],
            'notes_tone' => $diff == 0 ? 'good' : 'bad',
        ];
    }

    /* ------------------------------------------------------ piutang / hutang */

    private function bucketIndex(?string $due, string $asOf): int
    {
        if (! $due) return 0;
        $days = Carbon::parse($due)->startOfDay()->diffInDays(Carbon::parse($asOf)->startOfDay(), false);
        if ($days <= 0) return 0;
        return $days <= 30 ? 1 : ($days <= 60 ? 2 : ($days <= 90 ? 3 : 4));
    }

    private function aging(Request $r, string $kind): array
    {
        $isAr = $kind === 'ar';
        $asOf = $r->input('as_of_date', Carbon::now()->toDateString());
        $model = $isAr ? SalesInvoice::class : PurchaseInvoice::class;
        $invoices = $model::where('company_id', $this->companyId)
            ->whereNotIn('status', ['paid', 'void', 'draft'])
            ->where('amount_due', '>', 0)
            ->with($isAr ? 'customer' : 'supplier')
            ->orderBy('due_date')->get();

        $rows = []; $tot = array_fill(0, 5, 0.0);
        foreach ($invoices as $inv) {
            $i = $this->bucketIndex($inv->due_date?->toDateString(), $asOf);
            $cells = [$inv->invoice_number, ($isAr ? $inv->customer?->name : $inv->supplier?->name) ?? '-', $inv->invoice_date, $inv->due_date, 0, 0, 0, 0, 0, (float) $inv->amount_due];
            $cells[4 + $i] = (float) $inv->amount_due;
            $tot[$i] += (float) $inv->amount_due;
            $rows[] = $this->row($cells);
        }
        $rows[] = ['type' => 'grand', 'cells' => array_merge(['', 'TOTAL', '', ''], $tot, [array_sum($tot)])];

        $who = $isAr ? 'Pelanggan' : 'Pemasok';
        return [
            'key' => $isAr ? 'receivable-aging' : 'payable-aging',
            'title' => $isAr ? 'Umur Piutang (Aging)' : 'Umur Hutang (Aging)',
            'subtitle' => 'Per ' . \App\Support\ReportFormat::date($asOf),
            'file' => ($isAr ? 'Umur_Piutang_' : 'Umur_Hutang_') . $asOf, 'orientation' => 'landscape',
            'filters' => [['name' => 'as_of_date', 'label' => 'Per Tanggal', 'type' => 'date', 'value' => $asOf]],
            'columns' => [
                ['key' => 'no', 'label' => 'No. Faktur', 'width' => 20],
                ['key' => 'who', 'label' => $who, 'width' => 30],
                ['key' => 'date', 'label' => 'Tgl Faktur', 'width' => 14, 'format' => 'date'],
                ['key' => 'due', 'label' => 'Jatuh Tempo', 'width' => 14, 'format' => 'date'],
                ['key' => 'b0', 'label' => 'Belum JT', 'width' => 16, 'align' => 'right', 'format' => 'money'],
                ['key' => 'b1', 'label' => '1–30 hari', 'width' => 16, 'align' => 'right', 'format' => 'money'],
                ['key' => 'b2', 'label' => '31–60 hari', 'width' => 16, 'align' => 'right', 'format' => 'money'],
                ['key' => 'b3', 'label' => '61–90 hari', 'width' => 16, 'align' => 'right', 'format' => 'money'],
                ['key' => 'b4', 'label' => '> 90 hari', 'width' => 16, 'align' => 'right', 'format' => 'money'],
                ['key' => 'tot', 'label' => 'Total (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows, 'empty' => 'Tidak ada ' . ($isAr ? 'piutang' : 'hutang') . ' yang belum lunas.',
            'summary' => [
                ['label' => 'Total ' . ($isAr ? 'Piutang' : 'Hutang'), 'value' => array_sum($tot), 'format' => 'money'],
                ['label' => 'Sudah Jatuh Tempo', 'value' => array_sum(array_slice($tot, 1)), 'format' => 'money', 'tone' => array_sum(array_slice($tot, 1)) > 0 ? 'bad' : null],
            ],
        ];
    }

    public function receivableAging(Request $r): array { return $this->aging($r, 'ar'); }
    public function payableAging(Request $r): array { return $this->aging($r, 'ap'); }

    private function partySummary(string $kind): array
    {
        $isAr = $kind === 'ar';
        $model = $isAr ? SalesInvoice::class : PurchaseInvoice::class;
        $invoices = $model::where('company_id', $this->companyId)->whereNotIn('status', ['void', 'draft'])
            ->with($isAr ? 'customer' : 'supplier')->get()
            ->groupBy($isAr ? 'customer_id' : 'supplier_id');

        $rows = []; $t = [0, 0, 0, 0];
        $list = $invoices->map(function ($g) use ($isAr) {
            $p = $isAr ? $g->first()->customer : $g->first()->supplier;
            return [$p?->name ?? '-', $g->count(), (float) $g->sum('total'), (float) $g->sum('amount_paid'), (float) $g->sum('amount_due')];
        })->filter(fn ($x) => $x[4] > 0)->sortByDesc(fn ($x) => $x[4]);

        foreach ($list as $x) {
            $rows[] = $this->row($x);
            $t[0] += $x[1]; $t[1] += $x[2]; $t[2] += $x[3]; $t[3] += $x[4];
        }
        $rows[] = ['type' => 'grand', 'cells' => ['TOTAL', $t[0], $t[1], $t[2], $t[3]]];

        return [
            'key' => $isAr ? 'receivable-summary' : 'payable-summary',
            'title' => $isAr ? 'Rekap Piutang per Pelanggan' : 'Rekap Hutang per Pemasok',
            'subtitle' => 'Per ' . \App\Support\ReportFormat::date(now()),
            'file' => ($isAr ? 'Rekap_Piutang_' : 'Rekap_Hutang_') . now()->toDateString(),
            'filters' => [],
            'columns' => [
                ['key' => 'who', 'label' => $isAr ? 'Pelanggan' : 'Pemasok', 'width' => 40],
                ['key' => 'n', 'label' => 'Jml Faktur', 'width' => 12, 'align' => 'right', 'format' => 'int'],
                ['key' => 'total', 'label' => 'Total Tagihan (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'money'],
                ['key' => 'paid', 'label' => 'Terbayar (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'money'],
                ['key' => 'due', 'label' => $isAr ? 'Sisa Piutang (Rp)' : 'Sisa Hutang (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows, 'empty' => 'Tidak ada data.',
            'summary' => [['label' => $isAr ? 'Total Piutang' : 'Total Hutang', 'value' => $t[3], 'format' => 'money']],
        ];
    }

    public function receivableSummary(Request $r): array { return $this->partySummary('ar'); }
    public function payableSummary(Request $r): array { return $this->partySummary('ap'); }

    /* ------------------------------------------------------ daftar faktur */

    private function invoiceList(Request $r, string $kind): array
    {
        $isAr = $kind === 'ar';
        $start = $r->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $end = $r->input('end_date', Carbon::now()->toDateString());
        $model = $isAr ? SalesInvoice::class : PurchaseInvoice::class;
        $labels = ['unpaid' => 'Belum Dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'draft' => 'Draft', 'sent' => 'Terkirim'];

        $invoices = $model::where('company_id', $this->companyId)->where('status', '!=', 'void')
            ->whereDate('invoice_date', '>=', $start)->whereDate('invoice_date', '<=', $end)
            ->with($isAr ? 'customer' : 'supplier')->orderBy('invoice_date')->orderBy('id')->get();

        $rows = []; $t = [0, 0, 0];
        foreach ($invoices as $inv) {
            $rows[] = $this->row([
                $inv->invoice_number, $inv->invoice_date, ($isAr ? $inv->customer?->name : $inv->supplier?->name) ?? '-',
                $labels[$inv->status] ?? ucfirst($inv->status), (float) $inv->total, (float) $inv->amount_paid, (float) $inv->amount_due,
            ]);
            $t[0] += $inv->total; $t[1] += $inv->amount_paid; $t[2] += $inv->amount_due;
        }
        $rows[] = ['type' => 'grand', 'cells' => ['', '', 'TOTAL', '', $t[0], $t[1], $t[2]]];

        return [
            'key' => $isAr ? 'sales-report' : 'purchase-report',
            'title' => $isAr ? 'Daftar Faktur Penjualan' : 'Daftar Faktur Pembelian',
            'subtitle' => $this->period($start, $end),
            'file' => ($isAr ? 'Faktur_Penjualan_' : 'Faktur_Pembelian_') . "{$start}_sd_{$end}", 'orientation' => 'landscape',
            'filters' => $this->dateFilters($start, $end),
            'columns' => [
                ['key' => 'no', 'label' => 'No. Faktur', 'width' => 22],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 14, 'format' => 'date'],
                ['key' => 'who', 'label' => $isAr ? 'Pelanggan' : 'Pemasok', 'width' => 32],
                ['key' => 'status', 'label' => 'Status', 'width' => 16],
                ['key' => 'total', 'label' => 'Total (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
                ['key' => 'paid', 'label' => 'Terbayar (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
                ['key' => 'due', 'label' => 'Sisa (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows, 'empty' => 'Tidak ada faktur pada periode ini.',
            'summary' => [
                ['label' => 'Jumlah Faktur', 'value' => $invoices->count(), 'format' => 'int'],
                ['label' => $isAr ? 'Total Penjualan' : 'Total Pembelian', 'value' => $t[0], 'format' => 'money'],
                ['label' => 'Terbayar', 'value' => $t[1], 'format' => 'money'],
                ['label' => 'Belum Dibayar', 'value' => $t[2], 'format' => 'money', 'tone' => $t[2] > 0 ? 'bad' : null],
            ],
        ];
    }

    public function salesReport(Request $r): array { return $this->invoiceList($r, 'ar'); }
    public function purchaseReport(Request $r): array { return $this->invoiceList($r, 'ap'); }

    /* ---------------------------------------------------------- persediaan */

    public function inventoryStock(Request $r): array
    {
        $products = Product::where('company_id', $this->companyId)->where('type', 'goods')->with('category')->orderBy('name')->get();
        $rows = []; $tv = 0; $low = 0;
        foreach ($products as $p) {
            $stock = (float) $p->stock;
            $val = $stock * (float) $p->buy_price;
            $tv += $val;
            if ($stock <= 5) $low++;
            $rows[] = $this->row([$p->sku, $p->name, $p->category?->name ?? '-', $p->unit ?? '-', $stock, (float) $p->buy_price, $val]);
        }
        $rows[] = ['type' => 'grand', 'cells' => ['', 'TOTAL NILAI PERSEDIAAN', '', '', '', '', $tv]];

        return [
            'key' => 'inventory-stock', 'title' => 'Laporan Stok Persediaan', 'subtitle' => 'Per ' . \App\Support\ReportFormat::date(now()),
            'file' => 'Stok_Persediaan_' . now()->toDateString(), 'orientation' => 'landscape', 'filters' => [],
            'columns' => [
                ['key' => 'sku', 'label' => 'SKU', 'width' => 16],
                ['key' => 'name', 'label' => 'Nama Barang', 'width' => 36],
                ['key' => 'cat', 'label' => 'Kategori', 'width' => 20],
                ['key' => 'unit', 'label' => 'Satuan', 'width' => 10],
                ['key' => 'stock', 'label' => 'Stok', 'width' => 12, 'align' => 'right', 'format' => 'qty'],
                ['key' => 'cost', 'label' => 'Harga Beli (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
                ['key' => 'val', 'label' => 'Nilai (Rp)', 'width' => 20, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows, 'empty' => 'Belum ada barang.',
            'summary' => [
                ['label' => 'Jumlah Barang', 'value' => $products->count(), 'format' => 'int'],
                ['label' => 'Stok Menipis (≤5)', 'value' => $low, 'format' => 'int', 'tone' => $low > 0 ? 'bad' : null],
                ['label' => 'Nilai Persediaan', 'value' => $tv, 'format' => 'money'],
            ],
        ];
    }
}
