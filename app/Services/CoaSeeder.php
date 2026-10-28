<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DefaultAccount;
use App\Support\BusinessType;

/** Membuat bagan akun (CoA) perusahaan dari template super admin, sesuai bidang usahanya. */
class CoaSeeder
{
    /** Rows template: dari tabel default_accounts (bisa diubah super admin), cadangan: CoA standar bawaan. */
    private static function templateRows(): array
    {
        try {
            $rows = DefaultAccount::orderBy('sort_order')->orderBy('code')->get();
        } catch (\Throwable $e) {
            $rows = collect();
        }
        if ($rows->isNotEmpty()) {
            $codeById = $rows->pluck('code', 'id');

            return $rows->map(fn ($d) => [$d->code, $d->name, $d->type, $d->normal_balance, $d->parent_id ? ($codeById[$d->parent_id] ?? null) : null, (bool) $d->is_system, (string) $d->industries])->all();
        }

        return CoaTemplate::standard();
    }

    /** Template yang berlaku untuk bidang usaha tertentu; induk yang tidak berlaku dilewati (anak naik ke induk terdekat). */
    private static function rowsFor(?string $businessType): array
    {
        $wanted = BusinessType::industries($businessType);
        $all = self::templateRows();
        $byCode = [];
        foreach ($all as $r) {
            $byCode[$r[0]] = $r;
        }
        $keep = array_filter($all, fn ($r) => CoaTemplate::applies($r[6], $wanted));
        $keepCodes = array_flip(array_map(fn ($r) => $r[0], $keep));
        $out = [];
        foreach ($keep as $r) {
            $p = $r[4];
            while ($p && ! isset($keepCodes[$p])) {
                $p = $byCode[$p][4] ?? null;
            }
            $r[4] = $p;
            $out[] = $r;
        }

        return $out;
    }

    public static function seed(int $companyId, ?string $businessType = null): void
    {
        $businessType ??= optional(\App\Models\Company::find($companyId))->business_type;
        $ids = [];
        foreach (self::rowsFor($businessType) as [$code, $name, $type, $bal, $parent, $header, $ind]) {
            $ids[$code] = Account::create([
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $bal,
                'parent_id' => $parent ? ($ids[$parent] ?? null) : null, 'is_system' => $header,
            ])->id;
        }
    }

    /**
     * Tambahkan akun template bidang usaha yang belum dimiliki perusahaan (mis. setelah bidang usaha diganti).
     * Akun yang sudah ada (kode atau nama sama) tidak diubah. @return int jumlah akun yang ditambahkan
     */
    public static function addMissing(int $companyId, ?string $businessType): int
    {
        $existing = Account::where('company_id', $companyId)->get();
        $byCode = $existing->keyBy('code');
        $names = $existing->map(fn ($a) => mb_strtolower(trim($a->name)))->flip();
        $added = 0;
        foreach (self::rowsFor($businessType) as [$code, $name, $type, $bal, $parent, $header, $ind]) {
            if (isset($byCode[$code]) || isset($names[mb_strtolower($name)])) {
                continue;
            }
            $parentId = $parent && isset($byCode[$parent]) ? $byCode[$parent]->id : null;
            $acc = Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => $type,
                'normal_balance' => $bal, 'parent_id' => $parentId, 'is_system' => $header]);
            $byCode[$code] = $acc;
            $names[mb_strtolower($name)] = 1;
            $added++;
        }

        return $added;
    }
}
