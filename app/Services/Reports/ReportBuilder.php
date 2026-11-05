<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Customer;
use App\Models\DownPayment;
use App\Models\DownPaymentApplication;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Services\AccountResolver;
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

    private function sums(?string $from, string $to, array $excludeJournals = []): array
    {
        return JournalDetail::query()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)
            ->where('journals.status', 'posted')
            ->when($excludeJournals, fn ($q) => $q->whereNotIn('journals.id', $excludeJournals))
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
        $before = $this->sums(null, Carbon::parse($start)->subDay()->toDateString(), $hide);
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

    /* --------------------------------------------------------------- pajak */

    /**
     * Baris PPN satu sisi: faktur, retur, uang muka, dan pemakaian uang muka pada periode.
     *
     * @return array{lines: array, base: float, tax: float, free: float, missing: int, journals: array}
     */
    private function vatSide(string $kind, string $start, string $end): array
    {
        $sales = $kind === 'sales';
        $partyRel = $sales ? 'customer' : 'supplier';
        $period = fn ($q, string $col) => $q->where('company_id', $this->companyId)->whereDate($col, '>=', $start)->whereDate($col, '<=', $end);
        $rate = fn ($p) => rtrim(rtrim(number_format((float) $p, 2, ',', ''), '0'), ',') . '%';
        $lines = [];
        $journals = [];
        $free = 0.0;
        $missing = 0;
        // Pajak selain PPN (mis. PPh yang terlanjur dipilih di faktur) tidak dihitung sebagai PPN
        $notVat = \App\Models\Tax::where('company_id', $this->companyId)->get()->reject->isVat()->pluck('id')->map(fn ($i) => (int) $i)->all();
        $isVat = fn ($taxId) => ! in_array((int) $taxId, $notVat, true);
        $add = function ($date, string $doc, ?string $fp, $party, string $type, float $base, $percent, float $tax) use (&$lines, $rate) {
            $lines[] = ['date' => Carbon::parse($date)->toDateString(), 'doc' => $doc, 'fp' => $fp ?: '', 'party' => $party->name ?? '', 'npwp' => $party->npwp ?? '',
                'type' => $type, 'base' => round($base, 2), 'rate' => (float) $percent > 0 ? $rate($percent) : '', 'tax' => round($tax, 2)];
        };

        $invoices = $period(($sales ? SalesInvoice::query() : PurchaseInvoice::query())->with($partyRel), 'invoice_date')->where('status', '!=', 'void')->get();
        foreach ($invoices as $i) {
            $after = (float) $i->subtotal - (float) $i->discount_amount;
            if ((float) $i->tax_amount <= 0 || ! $isVat($i->tax_id)) {
                $free += $after;
                continue;
            }
            $free += $after - (float) $i->tax_base;
            $journals[] = $i->journal_id;
            $missing += $i->tax_invoice_number ? 0 : 1;
            $add($i->invoice_date, $i->invoice_number, $i->tax_invoice_number, $i->{$partyRel}, 'Faktur', (float) $i->tax_base, $i->tax_percent, (float) $i->tax_amount);
        }

        $invoiceRel = $sales ? 'salesInvoice' : 'purchaseInvoice';
        $returns = $period(($sales ? SalesReturn::query() : PurchaseReturn::query())->with($invoiceRel . '.' . $partyRel), 'return_date')->where('tax_amount', '>', 0)->get();
        foreach ($returns as $r) {
            $inv = $r->{$invoiceRel};
            if (! $isVat($inv?->tax_id)) {
                continue;
            }
            $journals[] = $r->journal_id;
            $add($r->return_date, $r->return_number, $inv?->tax_invoice_number, $inv?->{$partyRel}, 'Retur', -1 * (float) $r->tax_base, $inv?->tax_percent, -1 * (float) $r->tax_amount);
        }

        $advances = $period(DownPayment::query()->with($partyRel)->where('type', $kind), 'payment_date')->where('tax_amount', '>', 0)->get();
        foreach ($advances as $d) {
            if (! $isVat($d->tax_id)) {
                continue;
            }
            $journals[] = $d->journal_id;
            $missing += $d->tax_invoice_number ? 0 : 1;
            $add($d->payment_date, $d->payment_number, $d->tax_invoice_number, $d->{$partyRel}, 'Uang Muka', (float) $d->tax_base, $d->tax_percent, (float) $d->tax_amount);
        }

        $fk = $sales ? 'sales_invoice_id' : 'purchase_invoice_id';
        $used = $period(DownPaymentApplication::query()->with(['downPayment.' . $partyRel, $invoiceRel]), 'applied_date')
            ->whereNull('voided_at')->whereNotNull($fk)->where('tax_amount', '>', 0)->get();
        foreach ($used as $a) {
            $d = $a->downPayment;
            if (! $isVat($d?->tax_id)) {
                continue;
            }
            $journals[] = $a->journal_id;
            $add($a->applied_date, (string) $d?->payment_number, $d?->tax_invoice_number, $d?->{$partyRel}, 'Pakai Uang Muka ' . $a->{$invoiceRel}?->invoice_number,
                -1 * ((float) $a->amount - (float) $a->tax_amount), $d?->tax_percent, -1 * (float) $a->tax_amount);
        }

        usort($lines, fn ($x, $y) => [$x['date'], $x['doc']] <=> [$y['date'], $y['doc']]);

        return ['lines' => $lines, 'base' => round(array_sum(array_column($lines, 'base')), 2), 'tax' => round(array_sum(array_column($lines, 'tax')), 2),
            'free' => round($free, 2), 'missing' => $missing, 'journals' => array_values(array_filter($journals))];
    }

    /**
     * Mutasi akun PPN pada Buku Besar dan jurnal lain di luar dokumen ber-PPN (jurnal manual, kas, pembatalan setelah tutup buku).
     * Jurnal dan pembaliknya dalam periode yang sama saling meniadakan, jadi tidak ditampilkan.
     *
     * @param  bool  $creditNormal  true untuk Pajak Keluaran, false untuk Pajak Masukan
     * @return array{amount: float, others: array}
     */
    private function vatLedger(array $accountIds, string $start, string $end, bool $creditNormal, array $documentJournals): array
    {
        $details = JournalDetail::query()->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)->where('journals.status', 'posted')
            ->whereDate('journals.transaction_date', '>=', $start)->whereDate('journals.transaction_date', '<=', $end)
            ->whereIn('journal_details.account_id', $accountIds)
            ->get(['journal_details.debit', 'journal_details.credit', 'journals.id as jid', 'journals.ref_number', 'journals.transaction_date', 'journals.description as jdesc', 'journals.reverses_journal_id']);
        $signed = fn ($d) => $creditNormal ? (float) $d->credit - (float) $d->debit : (float) $d->debit - (float) $d->credit;

        $rest = $details->reject(fn ($d) => in_array((int) $d->jid, $documentJournals, true));
        $ids = $rest->pluck('jid')->map(fn ($i) => (int) $i)->all();
        $cancelled = [];
        foreach ($rest as $d) {
            if ($d->reverses_journal_id && in_array((int) $d->reverses_journal_id, $ids, true)) {
                $cancelled[] = (int) $d->jid;
                $cancelled[] = (int) $d->reverses_journal_id;
            }
        }
        $others = $rest->reject(fn ($d) => in_array((int) $d->jid, $cancelled, true))->groupBy('jid')->map(fn ($g) => [
            'date' => Carbon::parse($g->first()->transaction_date)->toDateString(), 'ref' => $g->first()->ref_number,
            'desc' => $g->first()->jdesc, 'amount' => round($g->sum($signed), 2),
        ])->filter(fn ($o) => abs($o['amount']) > 0.004)->sortBy('date')->values()->all();

        return ['amount' => round($details->sum($signed), 2), 'others' => $others];
    }

    /** Akun PPN perusahaan: yang dipetakan di master Pajak dan akun standar di Bagan Akun. */
    private function vatAccounts(): array
    {
        $taxes = \App\Models\Tax::where('company_id', $this->companyId)->get()->filter->isVat();
        $pick = fn (string $col, $fallback) => $taxes->pluck($col)->filter()->push(optional($fallback)->id)->filter()->map(fn ($i) => (int) $i)->unique()->values()->all();

        return [$pick('sales_tax_account_id', AccountResolver::outputTax($this->companyId)), $pick('purchase_tax_account_id', AccountResolver::inputTax($this->companyId))];
    }

    /** Kertas kerja PPN: daftar Pajak Keluaran dan Masukan, kurang atau lebih bayar, dan kecocokannya dengan Buku Besar. */
    public function vat(Request $r): array
    {
        $start = $r->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $end = $r->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $jenis = in_array($r->input('jenis'), ['keluaran', 'masukan'], true) ? $r->input('jenis') : 'semua';

        $out = $this->vatSide('sales', $start, $end);
        $in = $this->vatSide('purchase', $start, $end);
        [$outAccounts, $inAccounts] = $this->vatAccounts();
        // Satu akun untuk keluaran dan masukan: mutasinya tidak bisa dipisah per sisi, jadi dicocokkan selisih bersihnya.
        $shared = (bool) array_intersect($outAccounts, $inAccounts);
        $documentJournals = array_map('intval', array_merge($out['journals'], $in['journals']));

        $net = round($out['tax'] - $in['tax'], 2);
        $cols = [
            ['key' => 'date', 'label' => 'Tanggal', 'width' => 12, 'format' => 'date', 'nowrap' => true],
            ['key' => 'doc', 'label' => 'No. Dokumen', 'width' => 20, 'nowrap' => true],
            ['key' => 'fp', 'label' => 'No. Faktur Pajak', 'width' => 22, 'nowrap' => true],
            ['key' => 'party', 'label' => 'Pelanggan / Pemasok', 'width' => 30],
            ['key' => 'npwp', 'label' => 'NPWP', 'width' => 20, 'nowrap' => true],
            ['key' => 'type', 'label' => 'Jenis', 'width' => 15],
            ['key' => 'base', 'label' => 'DPP (Rp)', 'width' => 17, 'align' => 'right', 'format' => 'money'],
            ['key' => 'rate', 'label' => 'Tarif', 'width' => 7, 'align' => 'right'],
            ['key' => 'tax', 'label' => 'PPN (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'money'],
        ];
        $n = count($cols);
        $labelRow = function (string $type, string $label, $base, $tax, bool $italic = false) use ($n) {
            $cells = array_fill(0, $n, '');
            $cells[3] = $label;
            $cells[6] = $base;
            $cells[8] = $tax;

            return ['type' => $type, 'cells' => $cells, 'italic' => $italic];
        };
        $lineRow = fn (array $l) => $this->row([$l['date'], $l['doc'], $l['fp'], $l['party'], $l['npwp'], $l['type'], $l['base'], $l['rate'], $l['tax']]);
        $side = function (string $title, array $data, string $freeLabel) use ($labelRow, $lineRow) {
            $rows = [$this->sec($title)];
            foreach ($data['lines'] as $l) {
                $rows[] = $lineRow($l);
            }
            if (! $data['lines']) {
                $rows[] = $labelRow('row', 'Tidak ada transaksi ber-PPN pada periode ini.', '', '', true);
            }
            $rows[] = $labelRow('subtotal', 'Total ' . ucwords(strtolower($title)), $data['base'], $data['tax']);
            if ($data['free'] > 0.004) {
                $rows[] = $labelRow('row', $freeLabel . ' (informasi, tidak dihitung)', $data['free'], '', true);
            }

            return [...$rows, ['type' => 'spacer']];
        };
        $reconcile = function (string $label, float $doc, array $ledger) use ($labelRow) {
            $diff = round($doc - $ledger['amount'], 2);
            $rows = [
                $labelRow('row', $label . ' menurut dokumen', '', $doc),
                $labelRow('row', $label . ' menurut Buku Besar', '', $ledger['amount']),
            ];
            // Pemetaan akun yang keliru bisa memunculkan banyak jurnal; yang tampil dibatasi agar laporan tetap terbaca
            foreach (array_slice($ledger['others'], 0, 30) as $o) {
                $cells = array_fill(0, 9, '');
                $cells[0] = $o['date'];
                $cells[1] = $o['ref'];
                $cells[3] = $o['desc'];
                $cells[5] = 'Jurnal lain';
                $cells[8] = $o['amount'];
                $rows[] = ['type' => 'row', 'cells' => $cells, 'italic' => true];
            }
            if (($more = count($ledger['others']) - 30) > 0) {
                $rows[] = $labelRow('row', 'Dan ' . $more . ' jurnal lain. Lihat Buku Besar akun PPN untuk rincian lengkapnya.', '', '', true);
            }
            $rows[] = $labelRow('subtotal', 'Selisih ' . $label, '', $diff);

            return [$rows, $diff];
        };

        $rows = [];
        if ($jenis !== 'masukan') {
            $rows = [...$rows, ...$side('PAJAK KELUARAN', $out, 'Penjualan tanpa PPN')];
        }
        if ($jenis !== 'keluaran') {
            $rows = [...$rows, ...$side('PAJAK MASUKAN', $in, 'Pembelian tanpa PPN')];
        }
        if ($jenis === 'semua') {
            $rows[] = $this->sec('RINGKASAN');
            $rows[] = $labelRow('row', 'Pajak Keluaran', $out['base'], $out['tax']);
            $rows[] = $labelRow('row', 'Pajak Masukan', $in['base'], $in['tax']);
            $rows[] = $labelRow('grand', $net >= 0 ? 'KURANG BAYAR' : 'LEBIH BAYAR', '', abs($net));
            $rows[] = ['type' => 'spacer'];
        }

        // Rekonsiliasi
        $diffs = [];
        $recon = [];
        if ($shared) {
            $ledger = $this->vatLedger(array_values(array_unique([...$outAccounts, ...$inAccounts])), $start, $end, true, $documentJournals);
            [$recon, $d] = $reconcile('PPN bersih', $net, $ledger);
            $diffs[] = $d;
        } else {
            if ($jenis !== 'masukan') {
                [$rs, $d] = $reconcile('Pajak Keluaran', $out['tax'], $this->vatLedger($outAccounts, $start, $end, true, $documentJournals));
                $recon = [...$recon, ...$rs];
                $diffs[] = $d;
            }
            if ($jenis !== 'keluaran') {
                [$rs, $d] = $reconcile('Pajak Masukan', $in['tax'], $this->vatLedger($inAccounts, $start, $end, false, $documentJournals));
                $recon = [...$recon, ...$rs];
                $diffs[] = $d;
            }
        }
        $rows = [...$rows, $this->sec('KECOCOKAN DENGAN BUKU BESAR'), ...$recon];

        $totalDiff = round(array_sum(array_map('abs', $diffs)), 2);
        $missing = ($jenis !== 'masukan' ? $out['missing'] : 0) + ($jenis !== 'keluaran' ? $in['missing'] : 0);
        $notes = [];
        $notes[] = $totalDiff > 0.004
            ? ['tone' => 'bad', 'text' => 'Ada selisih dengan Buku Besar. Lihat bagian Kecocokan dengan Buku Besar: selisih biasanya berasal dari jurnal manual, pembayaran kas yang memakai akun PPN, pembatalan setelah tutup buku, atau akun di menu Pajak yang bukan akun PPN.']
            : ['tone' => 'good', 'text' => 'PPN pada dokumen cocok dengan Buku Besar.'];
        if ($missing) {
            $notes[] = ['tone' => 'bad', 'text' => $missing . ' dokumen ber-PPN belum punya No. Faktur Pajak. Isi dari halaman detail dokumennya atau daftar uang muka.'];
        }
        $notes[] = ['tone' => 'info', 'text' => 'Laporan ini kertas kerja untuk membantu pengisian SPT Masa PPN, bukan pengganti pelaporan di Coretax. Kompensasi lebih bayar dari masa sebelumnya belum diperhitungkan.'];

        return [
            'key' => 'vat', 'title' => 'Laporan PPN', 'subtitle' => $this->period($start, $end),
            'file' => "Laporan_PPN_{$start}_sd_{$end}", 'orientation' => 'landscape',
            'filters' => [...$this->dateFilters($start, $end), ['name' => 'jenis', 'label' => 'Jenis', 'type' => 'select', 'value' => $jenis,
                'options' => ['semua' => 'Keluaran & Masukan', 'keluaran' => 'Pajak Keluaran', 'masukan' => 'Pajak Masukan']]],
            'columns' => $cols, 'rows' => $rows,
            'summary' => [
                ['label' => 'PPN Keluaran', 'value' => $out['tax'], 'format' => 'money'],
                ['label' => 'PPN Masukan', 'value' => $in['tax'], 'format' => 'money'],
                ['label' => $net >= 0 ? 'Kurang Bayar' : 'Lebih Bayar', 'value' => abs($net), 'format' => 'money'],
                ['label' => 'Selisih dengan Buku Besar', 'value' => $totalDiff, 'format' => 'money', 'tone' => $totalDiff > 0.004 ? 'bad' : 'good'],
            ],
            'notes' => $notes,
        ];
    }

    /* ------------------------------------------------------ rekonsiliasi Coretax */

    /** Lampiran 1 SPT Tahunan PPh Badan: nilai komersial dari Buku Besar, kolom fiskal dari isian pengguna. */
    public function coretax(Request $r): array
    {
        $company = \App\Models\Company::find($this->companyId);
        $lampiran = array_key_exists($r->input('lampiran'), \App\Services\Coretax\CoretaxCatalog::LAMPIRAN) ? $r->input('lampiran') : \App\Services\Coretax\CoretaxCatalog::forBusinessType($company?->business_type);
        $years = self::coretaxYears($this->companyId);
        $year = in_array((int) $r->input('tahun'), $years, true) ? (int) $r->input('tahun') : $years[0];
        $detail = $r->input('tampilan', 'rinci') !== 'ringkas';
        $rec = (new \App\Services\Coretax\CoretaxReconciliation($this->companyId, $year, $lampiran))->build();
        $cols = [
            ['key' => 'code', 'label' => 'Kode Akun', 'width' => 9],
            ['key' => 'name', 'label' => 'Nama Akun', 'width' => 44],
            ['key' => 'commercial', 'label' => 'Nilai (Komersial)', 'width' => 17, 'align' => 'right', 'format' => 'money'],
            ['key' => 'non_object', 'label' => 'Tidak Termasuk Objek Pajak', 'width' => 15, 'align' => 'right', 'format' => 'money'],
            ['key' => 'final', 'label' => 'Dikenakan PPh Final', 'width' => 15, 'align' => 'right', 'format' => 'money'],
            ['key' => 'object', 'label' => 'Objek Pajak Tidak Final', 'width' => 15, 'align' => 'right', 'format' => 'money'],
            ['key' => 'positive', 'label' => 'Penyesuaian Fiskal Positif', 'width' => 15, 'align' => 'right', 'format' => 'money'],
            ['key' => 'negative', 'label' => 'Penyesuaian Fiskal Negatif', 'width' => 15, 'align' => 'right', 'format' => 'money'],
            ['key' => 'codes', 'label' => 'Kode Penyesuaian', 'width' => 12],
            ['key' => 'fiscal', 'label' => 'Nilai Fiskal', 'width' => 17, 'align' => 'right', 'format' => 'money'],
        ];
        $rows = [];
        $section = function (array $catalog) use (&$rows, $rec, $detail) {
            foreach ($catalog as $line) {
                if ($line['kind'] === 'header') {
                    $rows[] = $this->sec($line['name']);
                    continue;
                }
                $code = $line['code'];
                $v = $rec->values[$code] ?? [];
                $income = ! empty($line['income']);
                $cells = [$code, $line['name'], (float) ($v['commercial'] ?? 0),
                    $income ? (float) ($v['non_object'] ?? 0) : '',
                    $income ? (float) ($v['final'] ?? 0) : '',
                    $income ? (float) ($v['object'] ?? 0) : '',
                    (float) ($v['positive'] ?? 0),
                    (float) ($v['negative'] ?? 0),
                    (string) ($v['codes'] ?? ''),
                    (float) ($v['fiscal'] ?? 0)];
                $type = $line['kind'] === 'total' ? ($code === '4800' ? 'total' : 'subtotal') : 'row';
                $rows[] = ['type' => $type, 'cells' => $cells, 'indent' => $type === 'row' ? 1 : 0];
                if ($detail) {
                    foreach ($rec->accountsByCode[$code] ?? [] as $acc) {
                        $rows[] = ['type' => 'row', 'italic' => true, 'indent' => 3, 'cells' => ['', trim($acc['code'] . ' ' . $acc['name']), (float) $acc['amount'], '', '', '', '', '', '', '']];
                    }
                }
            }
        };
        $rows[] = $this->sec('LAPORAN LABA RUGI');
        $section(\App\Services\Coretax\CoretaxCatalog::profitLoss($lampiran));
        $position = $this->coretaxPosition($rec, $detail);

        $profit = $rec->value('4800');
        $book = \App\Services\EarningsLink::profit($this->companyId, $rec->start, $rec->end);
        $diffProfit = round($profit - $book, 2);
        $diffBalance = round($rec->value('1700') - $rec->value('3300'), 2);
        $notes = [];
        $notes[] = abs($diffProfit) < 0.01 && abs($diffBalance) < 0.01
            ? ['tone' => 'good', 'text' => 'Laba sebelum pajak cocok dengan Laba Rugi, dan Jumlah Aset sama dengan Jumlah Liabilitas dan Ekuitas.']
            : ['tone' => 'bad', 'text' => 'Ada selisih: laba rugi Rp ' . \App\Support\Money::fmt($diffProfit) . ', neraca Rp ' . \App\Support\Money::fmt($diffBalance) . '. Periksa pemetaan akunnya.'];
        if ($rec->needCheck) {
            $notes[] = ['tone' => 'bad', 'text' => $rec->needCheck . ' akun dipetakan hanya berdasarkan jenisnya. Periksa di halaman Pemetaan Akun.'];
        }
        $taxExpense = collect($rec->mapping)->first(fn ($m) => in_array($m['account']->type, ['expense', 'other_expense'], true)
            && preg_match('/\bpph\b|pajak penghasilan|pajak final/iu', $m['account']->name) && abs($m['amount']) > 0.004);
        if ($taxExpense) {
            $notes[] = ['tone' => 'info', 'text' => 'Beban pajak penghasilan seperti ' . $taxExpense['account']->name . ' masuk kode ' . $taxExpense['code'] . ' dan umumnya perlu penyesuaian fiskal positif. Isi di halaman Penyesuaian Fiskal.'];
        }
        if (in_array($lampiran, ['1B', '1C'], true)) {
            $notes[] = ['tone' => 'info', 'text' => 'Stok dicatat perpetual, jadi Pembelian dihitung dari HPP pembukuan ditambah persediaan akhir dikurangi persediaan awal. Total HPP selalu sama dengan pembukuan.'];
        }
        $notes[] = ['tone' => 'info', 'text' => $lampiran === '1C'
            ? 'Kertas kerja untuk membantu pengisian Lampiran 1 di Coretax, bukan pengganti pelaporan.'
            : 'Kertas kerja untuk membantu pengisian Lampiran 1 di Coretax, bukan pengganti pelaporan. Daftar kode ' . $lampiran . ' diambil dari sumber sekunder, cocokkan dengan tampilan Coretax.'];

        return [
            'key' => 'coretax', 'title' => 'Rekonsiliasi Coretax', 'subtitle' => \App\Services\Coretax\CoretaxCatalog::LAMPIRAN[$lampiran] . ' · ' . $this->period($rec->start, $rec->end),
            'file' => "Rekonsiliasi_Coretax_{$lampiran}_{$year}", 'orientation' => 'landscape',
            'filters' => [
                ['name' => 'tahun', 'label' => 'Tahun Pajak', 'type' => 'select', 'value' => $year, 'options' => array_combine($years, $years)],
                ['name' => 'lampiran', 'label' => 'Formulir', 'type' => 'select', 'value' => $lampiran, 'options' => \App\Services\Coretax\CoretaxCatalog::LAMPIRAN],
                ['name' => 'tampilan', 'label' => 'Tampilan', 'type' => 'select', 'value' => $detail ? 'rinci' : 'ringkas', 'options' => ['rinci' => 'Dengan rincian akun', 'ringkas' => 'Seperti formulir']],
            ],
            'links' => [
                ['label' => 'Pemetaan Akun', 'url' => route('client.reports.coretax.mapping', ['lampiran' => $lampiran, 'tahun' => $year])],
                ['label' => 'Penyesuaian Fiskal', 'url' => route('client.reports.coretax.fiscal', ['lampiran' => $lampiran, 'tahun' => $year])],
            ],
            'columns' => $cols, 'rows' => $rows, 'indent_col' => 1, 'table_class' => 'rpt-wide', 'side_by_side' => $position,
            'summary' => [
                ['label' => 'Laba (Rugi) Komersial', 'value' => $profit, 'format' => 'money'],
                ['label' => 'Laba (Rugi) Fiskal', 'value' => $rec->value('4800', 'fiscal'), 'format' => 'money'],
                ['label' => 'Jumlah Aset', 'value' => $rec->value('1700'), 'format' => 'money'],
                ['label' => 'Akun Perlu Dicek', 'value' => $rec->needCheck, 'format' => 'int', 'tone' => $rec->needCheck ? 'bad' : 'good'],
            ],
            'notes' => $notes,
        ];
    }

    /** Laporan Posisi Keuangan seperti formulir: Aset di kiri, Liabilitas dan Ekuitas di kanan, baris jumlah akhirnya sejajar. */
    private function coretaxPosition(\App\Services\Coretax\CoretaxReconciliation $rec, bool $detail): array
    {
        $sides = ['left' => [], 'right' => []];
        $side = 'left';
        foreach (\App\Services\Coretax\CoretaxCatalog::position() as $line) {
            if ($line['kind'] === 'header') {
                $side = str_starts_with($line['name'], 'Aset') ? 'left' : 'right';
                $sides[$side][] = $this->sec($line['name']);
                continue;
            }
            $code = $line['code'];
            $side = (int) $code[0] === 1 ? 'left' : 'right';
            $isTotal = $line['kind'] === 'total';
            $indent = ! $isTotal && preg_match('/^(Dikurangi|Cadangan)/', $line['name']) ? 1 : 0;
            $sides[$side][] = ['type' => $isTotal ? (in_array($code, ['1700', '3300'], true) ? 'total' : 'subtotal') : 'row', 'indent' => $indent,
                'cells' => [$code, $line['name'], $rec->value($code)]];
            if ($detail) {
                foreach ($rec->accountsByCode[$code] ?? [] as $acc) {
                    $sides[$side][] = ['type' => 'row', 'italic' => true, 'indent' => $indent + 1, 'cells' => ['', trim($acc['code'] . ' ' . $acc['name']), (float) $acc['amount']]];
                }
            }
        }
        // Sisi yang lebih pendek diberi baris kosong sebelum jumlah akhirnya
        $missing = count($sides['left']) - count($sides['right']);
        $short = $missing > 0 ? 'right' : 'left';
        if ($missing !== 0) {
            array_splice($sides[$short], -1, 0, array_fill(0, abs($missing), ['type' => 'spacer']));
        }

        return [
            'title' => 'LAPORAN POSISI KEUANGAN', 'sheet' => 'Posisi Keuangan',
            'columns' => [
                ['label' => 'Kode Akun', 'width' => 9],
                ['label' => 'Nama Akun', 'width' => 44],
                ['label' => 'Nilai', 'width' => 18, 'align' => 'right', 'format' => 'money'],
            ],
            'left' => $sides['left'], 'right' => $sides['right'],
        ];
    }

    /** Tahun yang punya jurnal, terbaru lebih dulu. Selalu memuat tahun berjalan. @return int[] */
    public static function coretaxYears(int $companyId): array
    {
        $journals = \App\Models\Journal::where('company_id', $companyId)->where('status', 'posted');
        $first = (clone $journals)->min('transaction_date');
        $last = (clone $journals)->max('transaction_date');
        $now = (int) date('Y');
        $to = $last ? max((int) substr((string) $last, 0, 4), $now) : $now;
        $from = $first ? min((int) substr((string) $first, 0, 4), $now) : $now;

        return range($to, $from);
    }

    /* ---------------------------------------------------------- persediaan */

    /** Saldo gabungan beberapa akun sampai tanggal tertentu, termasuk saldo awal akun. */
    private function balanceOf($accounts, string $to): float
    {
        $sums = $this->sums(null, $to);

        return $accounts->sum(function ($a) use ($sums) {
            [$d, $c] = $sums[$a->id] ?? [0, 0];

            return (float) $a->opening_balance + $d - $c;
        });
    }

    /** Mutasi (debit - kredit) akun tertentu pada jurnal yang disaring. */
    private function movementOf($accounts, string $start, string $end, callable $filter): float
    {
        $q = JournalDetail::query()->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)->where('journals.status', 'posted')
            ->whereDate('journals.transaction_date', '>=', $start)->whereDate('journals.transaction_date', '<=', $end)
            ->whereIn('journal_details.account_id', $accounts->pluck('id'));
        $filter($q);

        return (float) $q->sum('journal_details.debit') - (float) $q->sum('journal_details.credit');
    }

    /** Harga Pokok Produksi: bahan terpakai + tenaga kerja & overhead, hasil tiap barang jadi, dan mutasi persediaannya. */
    public function productionCost(Request $r): array
    {
        $start = $r->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $end = $r->input('end_date', Carbon::now()->toDateString());
        $orders = \App\Models\ProductionOrder::with(['product', 'items.product', 'costs.account'])->where('company_id', $this->companyId)->whereNull('voided_at')
            ->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->get();
        $journalIds = $orders->pluck('journal_id')->filter()->all();
        $tot = fn (string $type, string $label, $amount) => $this->tot($type, $label, $amount, 4);

        // Bahan terpakai per barang
        $materials = $orders->flatMap->items->groupBy('product_id')->map(fn ($g) => ['p' => $g->first()->product, 'qty' => (float) $g->sum('quantity'), 'total' => (float) $g->sum('total')])
            ->sortBy(fn ($m) => optional($m['p'])->name);
        $materialTotal = (float) $materials->sum('total');
        $rows = [$this->sec('BAHAN YANG DIPAKAI')];
        foreach ($materials as $m) {
            $rows[] = $this->row([optional($m['p'])->sku, optional($m['p'])->name, $m['qty'], $m['total']], 1);
        }
        $rows[] = $tot('subtotal', 'Total Bahan', $materialTotal);
        $rows[] = ['type' => 'spacer'];

        // Tenaga kerja & overhead yang dibebankan
        $costs = $orders->flatMap->costs->groupBy('account_id')->map(fn ($g) => ['a' => $g->first()->account, 'total' => (float) $g->sum('amount')])
            ->sortBy(fn ($c) => optional($c['a'])->code);
        $costTotal = (float) $costs->sum('total');
        $rows[] = $this->sec('TENAGA KERJA DAN OVERHEAD');
        foreach ($costs as $c) {
            $rows[] = $this->row([optional($c['a'])->code, optional($c['a'])->name, '', $c['total']], 1);
        }
        $rows[] = $tot('subtotal', 'Total Tenaga Kerja dan Overhead', $costTotal);
        $rows[] = $tot('total', 'HARGA POKOK PRODUKSI', $materialTotal + $costTotal);
        $rows[] = ['type' => 'spacer'];

        // Hasil per barang jadi
        $rows[] = $this->sec('HASIL PRODUKSI');
        foreach ($orders->groupBy('product_id') as $g) {
            $p = $g->first()->product;
            $qty = (float) $g->sum('quantity');
            $total = (float) $g->sum('total_cost');
            $rows[] = $this->row([optional($p)->sku, trim(optional($p)->name . ' (@ ' . number_format($qty > 0 ? $total / $qty : 0, 2, ',', '.') . ' / ' . optional($p)->unit . ')'), $qty, $total], 1);
        }
        $rows[] = ['type' => 'spacer'];

        // Mutasi persediaan dari Buku Besar
        $inventory = \App\Services\AccountResolver::inventoryAccounts($this->companyId);
        $before = Carbon::parse($start)->subDay()->toDateString();
        $flow = function (string $title, $accounts, string $inLabel, float $in, string $outLabel, float $out) use ($before, $end, $tot) {
            $open = $this->balanceOf($accounts, $before);
            $close = $this->balanceOf($accounts, $end);
            $other = $close - ($open + $in - $out);
            $list = [$this->sec($title), $this->row(['', 'Persediaan awal', '', $open], 1), $this->row(['', $inLabel, '', $in], 1), $this->row(['', $outLabel, '', -$out], 1)];
            if (abs($other) >= 0.01) {
                $list[] = $this->row(['', 'Penyesuaian, retur, dan lainnya', '', $other], 1);
            }
            $list[] = $tot('subtotal', 'Persediaan akhir', $close);

            return $list;
        };
        $materialAccounts = $inventory->filter(fn ($a) => \App\Support\ItemKind::isMaterial(\App\Support\ItemKind::fromAccountName($a->name)));
        $finishedAccounts = $inventory->filter(fn ($a) => \App\Support\ItemKind::fromAccountName($a->name) === \App\Support\ItemKind::FINISHED);
        $inOrders = fn ($q) => $q->whereIn('journals.id', $journalIds ?: [0]);
        $ofType = fn (string $type) => fn ($q) => $q->where('journals.type', $type);
        $rows = [...$rows, ...$flow('MUTASI PERSEDIAAN BAHAN', $materialAccounts, 'Pembelian', $this->movementOf($materialAccounts, $start, $end, $ofType('auto_purchase')),
            'Dipakai produksi', -$this->movementOf($materialAccounts, $start, $end, $inOrders)), ['type' => 'spacer']];
        $rows = [...$rows, ...$flow('MUTASI PERSEDIAAN BARANG JADI', $finishedAccounts, 'Hasil produksi', $this->movementOf($finishedAccounts, $start, $end, $inOrders),
            'Terjual (HPP)', -$this->movementOf($finishedAccounts, $start, $end, $ofType('auto_sales')))];

        return [
            'key' => 'production-cost', 'title' => 'Laporan Harga Pokok Produksi', 'subtitle' => $this->period($start, $end),
            'file' => "Harga_Pokok_Produksi_{$start}_sd_{$end}", 'filters' => $this->dateFilters($start, $end), 'indent_col' => 1,
            'columns' => [
                ['key' => 'code', 'label' => 'Kode', 'width' => 14],
                ['key' => 'name', 'label' => 'Keterangan', 'width' => 46],
                ['key' => 'qty', 'label' => 'Jumlah', 'width' => 14, 'align' => 'right', 'format' => 'qty'],
                ['key' => 'amount', 'label' => 'Nilai (Rp)', 'width' => 22, 'align' => 'right', 'format' => 'money'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Bahan', 'value' => $materialTotal, 'format' => 'money'],
                ['label' => 'Tenaga Kerja dan Overhead', 'value' => $costTotal, 'format' => 'money'],
                ['label' => 'Harga Pokok Produksi', 'value' => $materialTotal + $costTotal, 'format' => 'money'],
                ['label' => 'Perintah Produksi', 'value' => $orders->count(), 'format' => 'int'],
            ],
        ];
    }

    public function inventoryStock(Request $r): array
    {
        $products = Product::where('company_id', $this->companyId)->where('type', 'goods')->with('category')->orderBy('name')->get();
        $rows = []; $tv = 0; $low = 0;
        $method = optional(\App\Models\Company::find($this->companyId))->inventory_costing_method ?? 'average';
        foreach ($products as $p) {
            $stock = (float) $p->stock;
            $val = \App\Services\InventoryAccounting::stockValue($p, $method);
            $tv += $val;
            if ($stock <= 5) $low++;
            $rows[] = $this->row([$p->sku, $p->name, \App\Support\ItemKind::label($p->item_kind) ?? '-', $p->category?->name ?? '-', $p->unit ?? '-', $stock, $stock > 0 ? $val / $stock : (float) $p->buy_price, $val]);
        }
        $rows[] = ['type' => 'grand', 'cells' => ['', 'TOTAL NILAI PERSEDIAAN', '', '', '', '', '', $tv]];

        return [
            'key' => 'inventory-stock', 'title' => 'Laporan Stok Persediaan', 'subtitle' => 'Per ' . \App\Support\ReportFormat::date(now()),
            'file' => 'Stok_Persediaan_' . now()->toDateString(), 'orientation' => 'landscape', 'filters' => [],
            'columns' => [
                ['key' => 'sku', 'label' => 'SKU', 'width' => 16],
                ['key' => 'name', 'label' => 'Nama Barang', 'width' => 36],
                ['key' => 'kind', 'label' => 'Jenis', 'width' => 16],
                ['key' => 'cat', 'label' => 'Kategori', 'width' => 20],
                ['key' => 'unit', 'label' => 'Satuan', 'width' => 10],
                ['key' => 'stock', 'label' => 'Stok', 'width' => 12, 'align' => 'right', 'format' => 'qty'],
                ['key' => 'cost', 'label' => 'Harga Pokok (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'money'],
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
