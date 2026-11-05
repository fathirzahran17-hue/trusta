<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoretaxFiscalRow;
use App\Services\Coretax\CoretaxCatalog;
use App\Services\Coretax\CoretaxReconciliation;
use App\Services\Reports\ReportBuilder;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Halaman pendukung Rekonsiliasi Coretax: pemetaan akun dan penyesuaian fiskal. */
class CoretaxController extends Controller
{
    private const TYPE_LABELS = [
        'asset' => 'Aset', 'liability' => 'Liabilitas', 'equity' => 'Ekuitas', 'revenue' => 'Pendapatan', 'cost_of_sales' => 'Harga Pokok',
        'expense' => 'Beban Operasional', 'other_income' => 'Pendapatan Lain-lain', 'other_expense' => 'Beban Lain-lain',
    ];

    /** @return array{0:int,1:string,2:int,3:int[]} perusahaan, lampiran, tahun, daftar tahun */
    private function context(Request $r): array
    {
        $company = auth()->user()->company;
        $lampiran = array_key_exists($r->input('lampiran'), CoretaxCatalog::LAMPIRAN) ? $r->input('lampiran') : CoretaxCatalog::forBusinessType($company?->business_type);
        $years = ReportBuilder::coretaxYears((int) $company->id);
        $year = in_array((int) $r->input('tahun'), $years, true) ? (int) $r->input('tahun') : $years[0];

        return [(int) $company->id, $lampiran, $year, $years];
    }

    public function mapping(Request $r)
    {
        [$cid, $lampiran, $year, $years] = $this->context($r);
        $rec = (new CoretaxReconciliation($cid, $year, $lampiran))->build();
        $groups = collect($rec->mapping)->groupBy(fn ($m) => $m['account']->type)
            ->sortBy(fn ($rows, $type) => array_search($type, array_keys(self::TYPE_LABELS), true));
        $choices = collect(self::TYPE_LABELS)->keys()->mapWithKeys(fn ($type) => [$type => CoretaxCatalog::choices($type, $lampiran)]);

        return view('client.reports.coretax_mapping', [
            'lampiran' => $lampiran, 'year' => $year, 'years' => $years, 'groups' => $groups, 'choices' => $choices,
            'typeLabels' => self::TYPE_LABELS, 'needCheck' => $rec->needCheck, 'lampiranList' => CoretaxCatalog::LAMPIRAN,
        ]);
    }

    public function saveMapping(Request $r)
    {
        [$cid, $lampiran, $year] = $this->context($r);
        $data = $r->validate(['codes' => 'array', 'codes.*' => 'nullable|string|max:8']);
        $errors = [];
        foreach (Account::where('company_id', $cid)->get() as $a) {
            if ($a->is_header || ! array_key_exists($a->id, $data['codes'] ?? [])) {
                continue;
            }
            $code = $data['codes'][$a->id] ?: null;
            if ($code && ! CoretaxCatalog::isChoice($code, $a->type, $lampiran)) {
                $errors["codes.{$a->id}"] = "Kode {$code} tidak bisa dipakai untuk akun {$a->code} {$a->name}.";
                continue;
            }
            if ($a->coretax_code !== $code) {
                $a->update(['coretax_code' => $code]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return redirect()->route('client.reports.coretax.mapping', ['lampiran' => $lampiran, 'tahun' => $year])->with('success', 'Pemetaan akun disimpan.');
    }

    public function fiscal(Request $r)
    {
        [$cid, $lampiran, $year, $years] = $this->context($r);
        $rec = (new CoretaxReconciliation($cid, $year, $lampiran))->build();

        return view('client.reports.coretax_fiscal', [
            'lampiran' => $lampiran, 'year' => $year, 'years' => $years, 'lampiranList' => CoretaxCatalog::LAMPIRAN,
            'rows' => CoretaxCatalog::profitLoss($lampiran), 'values' => $rec->values, 'adjustmentCodes' => self::knownCodes(),
        ]);
    }

    public function saveFiscal(Request $r)
    {
        [$cid, $lampiran, $year] = $this->context($r);
        Money::normalize($r, ['rows.*.non_object', 'rows.*.final_income', 'rows.*.positive', 'rows.*.negative']);
        $data = $r->validate([
            'rows' => 'array',
            'rows.*.non_object' => 'nullable|numeric|min:0|max:9999999999999.99',
            'rows.*.final_income' => 'nullable|numeric|min:0|max:9999999999999.99',
            'rows.*.positive' => 'nullable|numeric|min:0|max:9999999999999.99',
            'rows.*.negative' => 'nullable|numeric|min:0|max:9999999999999.99',
            'rows.*.adjustment_codes' => 'nullable|string|max:120',
        ], [
            'rows.*.*.numeric' => 'Isi dengan angka.',
            'rows.*.*.min' => 'Nilai tidak boleh negatif.',
        ]);

        $catalog = collect(CoretaxCatalog::profitLoss($lampiran))->filter(fn ($l) => in_array($l['kind'], ['detail', 'derived'], true))->keyBy('code');
        $errors = [];
        $save = [];
        foreach ($data['rows'] ?? [] as $code => $row) {
            $line = $catalog[(string) $code] ?? null;
            if (! $line) {
                continue;
            }
            $income = ! empty($line['income']);
            $values = [
                'non_object' => $income ? (float) ($row['non_object'] ?? 0) : 0,
                'final_income' => $income ? (float) ($row['final_income'] ?? 0) : 0,
                'positive' => (float) ($row['positive'] ?? 0),
                'negative' => (float) ($row['negative'] ?? 0),
            ];
            [$codes, $invalid] = self::parseCodes((string) ($row['adjustment_codes'] ?? ''));
            if ($invalid) {
                $errors["rows.$code.adjustment_codes"] = "Kode $code: kode penyesuaian " . implode(', ', $invalid) . ' tidak dikenal. Gunakan FPO-01 sampai FPO-12 atau FNE-01 sampai FNE-04.';
            }
            if ($values['positive'] > 0 && ! array_filter($codes, fn ($c) => str_starts_with($c, 'FPO-'))) {
                $errors["rows.$code.adjustment_codes"] = "Kode $code: penyesuaian positif perlu kode FPO.";
            }
            if ($values['negative'] > 0 && ! array_filter($codes, fn ($c) => str_starts_with($c, 'FNE-'))) {
                $errors["rows.$code.adjustment_codes"] = "Kode $code: penyesuaian negatif perlu kode FNE.";
            }
            $save[(string) $code] = $values + ['adjustment_codes' => $codes ? implode(', ', $codes) : null];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        foreach ($save as $code => $values) {
            $keys = ['company_id' => $cid, 'tax_year' => $year, 'code' => (string) $code];
            $empty = ! array_filter([$values['non_object'], $values['final_income'], $values['positive'], $values['negative']]) && ! $values['adjustment_codes'];
            if ($empty) {
                CoretaxFiscalRow::where($keys)->delete();
            } else {
                CoretaxFiscalRow::updateOrCreate($keys, $values);
            }
        }

        return redirect()->route('client.reports.coretax.fiscal', ['lampiran' => $lampiran, 'tahun' => $year])->with('success', 'Penyesuaian fiskal disimpan.');
    }

    /** @return array{0:string[],1:string[]} kode yang sah, kode yang tidak dikenal */
    private static function parseCodes(string $raw): array
    {
        $parts = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', strtoupper(trim($raw))))));
        $valid = array_values(array_filter($parts, fn ($c) => preg_match('/^(FPO-(0[1-9]|1[0-2])|FNE-0[1-4])$/', $c)));

        return [$valid, array_values(array_diff($parts, $valid))];
    }

    /** Hanya kode yang artinya sama di dua sumber yang dituliskan penjelasannya. Kode lain tetap bisa diketik, cek artinya di Coretax. */
    private static function knownCodes(): array
    {
        return [
            'FPO-01' => 'Biaya untuk kepentingan pribadi', 'FPO-02' => 'Premi asuransi pribadi', 'FPO-04' => 'Imbalan terkait hubungan istimewa',
            'FPO-11' => 'Biaya 3M atas penghasilan final atau non-objek', 'FPO-12' => 'Penyesuaian positif lainnya',
            'FNE-01' => 'Penghasilan final atau non-objek', 'FNE-02' => 'Selisih penyusutan (komersial lebih kecil dari fiskal)',
            'FNE-03' => 'Selisih amortisasi (komersial lebih kecil dari fiskal)', 'FNE-04' => 'Penyesuaian negatif lainnya',
        ];
    }
}
