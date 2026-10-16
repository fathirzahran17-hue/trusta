<?php

namespace App\Services;

use App\Models\Account;

/**
 * Mencari akun sistem (Piutang, Hutang, HPP, Persediaan) untuk jurnal otomatis.
 *
 * Kode lama mencari akun dengan type 'account_receivable' / 'account_payable' yang
 * tidak pernah ada di COA (tipe akun hanya asset, liability, equity, revenue, dst.),
 * sehingga faktur selalu gagal disimpan. Sekarang dicari berdasarkan tipe lama
 * (kompatibilitas) lalu berdasarkan nama akun standar.
 */
class AccountResolver
{
    private static function find(int $companyId, string $type, array $namePatterns, array $legacyTypes = []): ?Account
    {
        foreach ($legacyTypes as $legacy) {
            if ($a = Account::where('company_id', $companyId)->where('type', $legacy)->first()) {
                return $a;
            }
        }

        foreach ($namePatterns as $pattern) {
            // is_header adalah accessor (bukan kolom), jadi disaring di PHP
            $a = Account::where('company_id', $companyId)
                ->where('type', $type)
                ->where('is_active', true)
                ->where('name', 'like', $pattern)
                ->orderBy('code')
                ->get()
                ->first(fn ($acc) => !$acc->is_header);
            if ($a) {
                return $a;
            }
        }

        return null;
    }

    public static function receivable(int $companyId): ?Account
    {
        return self::find($companyId, 'asset', ['Piutang Dagang%', 'Piutang Usaha%', 'Piutang%'], ['account_receivable']);
    }

    public static function payable(int $companyId): ?Account
    {
        return self::find($companyId, 'liability', ['Hutang Dagang%', 'Hutang Usaha%', 'Utang Usaha%', 'Utang Dagang%'], ['account_payable']);
    }

    public static function cogs(int $companyId): ?Account
    {
        return self::find($companyId, 'cost_of_sales', ['HPP%Barang Dagang%', 'HPP%', 'Harga Pokok%']);
    }

    public static function inventory(int $companyId): ?Account
    {
        return self::find($companyId, 'asset', ['Persediaan Barang Dagang%', 'Persediaan%']);
    }
}
