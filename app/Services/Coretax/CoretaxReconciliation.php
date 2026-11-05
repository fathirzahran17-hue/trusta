<?php

namespace App\Services\Coretax;

use App\Models\Account;
use App\Models\CoretaxFiscalRow;
use App\Models\JournalDetail;
use App\Services\EarningsLink;
use Carbon\Carbon;

/**
 * Menyusun Lampiran 1 dari Buku Besar: nilai komersial, kolom fiskal yang diisi pengguna, dan rincian akun di tiap kode.
 * Stok dicatat perpetual, jadi Pembelian dihitung dari HPP pembukuan dan selisih persediaan. Total HPP selalu sama dengan pembukuan.
 */
class CoretaxReconciliation
{
    public string $start;
    public string $end;
    /** @var array<string,array<string,float>> kode => kolom */
    public array $values = [];
    /** @var array<string,array<int,array<string,mixed>>> kode => akun */
    public array $accountsByCode = [];
    /** @var array<int,array<string,mixed>> */
    public array $mapping = [];
    public int $needCheck = 0;
    public array $fiscal = [];

    public function __construct(private int $companyId, public int $year, public string $lampiran) {}

    /** Tahun pajak mengikuti awal tahun fiskal perusahaan. */
    public static function range(int $companyId, int $year): array
    {
        $month = (int) (optional(\App\Models\Company::find($companyId))->fiscal_year_start ?: 1);
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start->toDateString(), $start->copy()->addYear()->subDay()->toDateString()];
    }

    private function sums(?string $from, string $to): array
    {
        return JournalDetail::query()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->companyId)->where('journals.status', 'posted')
            ->whereDate('journals.transaction_date', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('journals.transaction_date', '>=', $from))
            ->groupBy('journal_details.account_id')
            ->selectRaw('journal_details.account_id as aid, SUM(journal_details.debit) as d, SUM(journal_details.credit) as c')
            ->get()->keyBy('aid')->map(fn ($r) => [(float) $r->d, (float) $r->c])->all();
    }

    public function build(): self
    {
        [$this->start, $this->end] = self::range($this->companyId, $this->year);
        $accounts = Account::where('company_id', $this->companyId)->orderBy('code')->get();
        $mapper = new CoretaxMapper($accounts, $this->lampiran);
        $yearSums = $this->sums($this->start, $this->end);
        $toEnd = $this->sums(null, $this->end);
        $toOpen = $this->sums(null, Carbon::parse($this->start)->subDay()->toDateString());
        $plRows = collect(CoretaxCatalog::profitLoss($this->lampiran))->filter(fn ($r) => isset($r['code']))->keyBy('code');

        $this->fiscal = CoretaxFiscalRow::where('company_id', $this->companyId)->where('tax_year', $this->year)->get()->keyBy('code')->all();

        $add = function (string $code, float $v, array $info) {
            $this->values[$code]['commercial'] = ($this->values[$code]['commercial'] ?? 0) + $v;
            $this->accountsByCode[$code][] = $info + ['amount' => $v];
        };
        $inventory = ['raw' => [0.0, 0.0], 'wip' => [0.0, 0.0], 'finished' => [0.0, 0.0]];
        $hppPool = 0.0;
        $hppCodes = $this->hppInputCodes();

        foreach ($accounts as $a) {
            if ($a->is_header) {
                continue;
            }
            ['code' => $code, 'status' => $status] = $mapper->resolve($a);
            $info = ['id' => $a->id, 'code' => $a->code, 'name' => $a->name];
            $balanceSheet = in_array($a->type, ['asset', 'liability', 'equity'], true);
            if ($balanceSheet) {
                $debitNormal = $a->type === 'asset';
                [$d, $c] = $toEnd[$a->id] ?? [0, 0];
                [$od, $oc] = $toOpen[$a->id] ?? [0, 0];
                $value = (float) $a->opening_balance + ($debitNormal ? $d - $c : $c - $d);
                if ($code === '1401') {
                    $role = $this->inventoryRole($a->name);
                    $inventory[$role][0] += (float) $a->opening_balance + $od - $oc;
                    $inventory[$role][1] += $value;
                }
            } else {
                [$d, $c] = $yearSums[$a->id] ?? [0, 0];
                $dc = $plRows[$code]['dc'] ?? 'd';
                $value = $dc === 'c' ? $c - $d : $d - $c;
                if (in_array($code, $hppCodes, true)) {
                    $hppPool += $d - $c;
                }
            }
            if (abs($value) > 0.004) {
                $add($code, $value, $info);
            }
            $this->needCheck += $status === CoretaxMapper::CHECK ? 1 : 0;
            $this->mapping[] = ['account' => $a, 'code' => $code, 'status' => $status, 'amount' => $value];
        }

        // Laba rugi yang belum ditutup masuk Laba Ditahan, sama seperti Neraca
        $link = EarningsLink::split($this->companyId, $this->end);
        $earned = (float) $link['prior'] + (float) $link['current'];
        if (abs($earned) > 0.004) {
            $add('3200', $earned, ['id' => null, 'code' => '', 'name' => 'Laba (rugi) dari laba rugi (otomatis)']);
        }

        $this->deriveCostOfGoods($inventory, $hppPool);
        $this->applyFormulas(CoretaxCatalog::profitLoss($this->lampiran));
        $this->applyFormulas(CoretaxCatalog::position());

        return $this;
    }

    /** Kode HPP yang diisi dari akun. Bagian lainnya dihitung sistem. */
    private function hppInputCodes(): array
    {
        return match ($this->lampiran) {
            '1B' => ['5022', '5029', '5050', '5051', '5052', '5058', '5059', '5069'],
            '1D' => ['5020'],
            default => ['5001', '5003', '5007'],
        };
    }

    private function inventoryRole(string $name): string
    {
        $n = mb_strtolower($name);
        if ($this->lampiran !== '1B') {
            return 'finished';
        }
        if (str_contains($n, 'dalam proses')) return 'wip';
        if (str_contains($n, 'bahan')) return 'raw';

        return 'finished';
    }

    private function deriveCostOfGoods(array $inventory, float $pool): void
    {
        if ($this->lampiran === '1D') {
            return;
        }
        $set = function (string $code, float $v) { $this->values[$code]['commercial'] = $v; };
        $get = fn (string $code) => (float) ($this->values[$code]['commercial'] ?? 0);
        [$finishedOpen, $finishedEnd] = $inventory['finished'];
        $set('5008', $finishedOpen);
        $set('5009', $finishedEnd);
        if ($this->lampiran === '1B') {
            [$rawOpen, $rawEnd] = $inventory['raw'];
            [$wipOpen, $wipEnd] = $inventory['wip'];
            $set('5021', $rawOpen);
            $set('5032', $rawEnd);
            $set('5090', $wipOpen);
            $set('5099', $wipEnd);
            $conversion = $get('5050') + $get('5051') + $get('5052') + $get('5058') + $get('5059') + $get('5069');
            // Pembelian dicari supaya rumus formulir (5021 + 5022 - 5029 - 5032 + biaya konversi + 5090 - 5099 + 5008 - 5009) sama dengan HPP pembukuan
            $set('5022', $pool - $conversion + $get('5029') - $rawOpen + $rawEnd - $wipOpen + $wipEnd - $finishedOpen + $finishedEnd);
            return;
        }
        $set('5001', $pool - $get('5003') - $get('5007') - $finishedOpen + $finishedEnd);
    }

    private function applyFormulas(array $rows): void
    {
        foreach ($rows as $r) {
            if (! isset($r['code'])) {
                continue;
            }
            $code = $r['code'];
            if ($r['kind'] === 'total') {
                $totals = ['commercial' => 0.0, 'fiscal' => 0.0, 'non_object' => 0.0, 'final' => 0.0, 'positive' => 0.0, 'negative' => 0.0];
                foreach ($r['sum'] as $op) {
                    $sign = $op[0] === '-' ? -1 : 1;
                    $other = $this->values[substr($op, 1)] ?? [];
                    foreach (['commercial', 'fiscal', 'non_object', 'final'] as $col) {
                        $totals[$col] += $sign * (float) ($other[$col] ?? 0);
                    }
                    // Jumlah koreksi tetap dijumlahkan apa adanya: kolom (7) selalu menaikkan laba fiskal, kolom (8) selalu menurunkannya
                    $totals['positive'] += (float) ($other['positive'] ?? 0);
                    $totals['negative'] += (float) ($other['negative'] ?? 0);
                }
                $this->values[$code] = $totals + ['object' => $totals['commercial'] - $totals['non_object'] - $totals['final'], 'codes' => ''];
                continue;
            }
            $commercial = (float) ($this->values[$code]['commercial'] ?? 0);
            $row = $this->fiscal[$code] ?? null;
            $income = ! empty($r['income']);
            $nonObject = $income ? (float) ($row->non_object ?? 0) : 0.0;
            $final = $income ? (float) ($row->final_income ?? 0) : 0.0;
            $positive = (float) ($row->positive ?? 0);
            $negative = (float) ($row->negative ?? 0);
            $object = $income ? $commercial - $nonObject - $final : 0.0;
            // Baris beban: koreksi positif mengurangi beban yang diakui, koreksi negatif menambahnya
            $fiscal = match (true) {
                $income => $object + $positive - $negative,
                ! empty($r['raises']) => $commercial + $positive - $negative,
                default => $commercial - $positive + $negative,
            };
            $this->values[$code] = ['commercial' => $commercial, 'non_object' => $nonObject, 'final' => $final, 'object' => $object,
                'positive' => $positive, 'negative' => $negative, 'codes' => (string) ($row->adjustment_codes ?? ''), 'fiscal' => $fiscal];
        }
    }

    public function value(string $code, string $column = 'commercial'): float
    {
        return (float) ($this->values[$code][$column] ?? 0);
    }
}
