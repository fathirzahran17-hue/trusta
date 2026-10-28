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

    public static function salesRevenue(int $companyId): ?Account
    {
        return self::find($companyId, 'revenue', ['Pendapatan Penjualan%', 'Penjualan%', 'Pendapatan%']);
    }

    public static function serviceRevenue(int $companyId): ?Account
    {
        return self::find($companyId, 'revenue', ['Pendapatan Jasa%', 'Jasa%', 'Pendapatan%']);
    }

    /** Akun biaya langsung untuk jasa yang dikerjakan subkontraktor; dibuat otomatis bila belum ada. */
    public static function subcontractCost(int $companyId): ?Account
    {
        if ($a = self::find($companyId, 'cost_of_sales', ['%Subkon%'])) {
            return $a;
        }
        $parent = Account::where('company_id', $companyId)->where('type', 'cost_of_sales')->whereNull('parent_id')->orderBy('code')->first();
        if (!$parent) {
            return self::find($companyId, 'cost_of_sales', ['HPP%', 'Harga Pokok%']);
        }
        $code = '5-0005';
        while (Account::where('company_id', $companyId)->where('code', $code)->exists()) {
            $code = '5-' . str_pad((int) substr($code, 2) + 1, 4, '0', STR_PAD_LEFT);
        }

        return Account::create(['company_id' => $companyId, 'code' => $code, 'name' => 'HPP - Jasa Subkontrak', 'type' => 'cost_of_sales',
            'normal_balance' => 'debit', 'parent_id' => $parent->id, 'is_system' => false, 'is_active' => true]);
    }

    public static function cogs(int $companyId): ?Account
    {
        return self::find($companyId, 'cost_of_sales', ['HPP%Barang Dagang%', 'HPP%Barang Jadi%', 'HPP%', 'Harga Pokok%']);
    }

    public static function inventory(int $companyId): ?Account
    {
        return self::find($companyId, 'asset', ['Persediaan Barang Dagang%', 'Persediaan Barang Jadi%', 'Persediaan%']);
    }

    /** Akun persediaan sebuah barang: akun khusus di master barang (mis. Bahan Baku), bila kosong akun persediaan umum. */
    public static function inventoryFor(?\App\Models\Product $product, int $companyId): ?Account
    {
        if ($product && $product->inventory_account_id
            && ($a = Account::where('company_id', $companyId)->where('is_active', true)->find($product->inventory_account_id))) {
            return $a;
        }

        return self::inventory($companyId);
    }

    /** Semua akun persediaan (aset bernama "Persediaan ...", bukan induk) — untuk pilihan di master barang. */
    public static function inventoryAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)->where('type', 'asset')->where('is_active', true)->where('name', 'like', 'Persediaan%')
            ->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
    }

    /** Akun Pajak Keluaran (PPN jual) — dari pengaturan pajak, kalau kosong cari berdasarkan nama. */
    public static function outputTax(int $companyId, ?\App\Models\Tax $tax = null): ?Account
    {
        if ($tax && $tax->sales_tax_account_id && ($a = Account::where('company_id', $companyId)->find($tax->sales_tax_account_id))) return $a;
        return self::find($companyId, 'liability', ['Pajak Keluaran%', 'Hutang PPN%']);
    }

    /** Akun Pajak Masukan (PPN beli). */
    public static function inputTax(int $companyId, ?\App\Models\Tax $tax = null): ?Account
    {
        if ($tax && $tax->purchase_tax_account_id && ($a = Account::where('company_id', $companyId)->find($tax->purchase_tax_account_id))) return $a;
        return self::find($companyId, 'asset', ['Pajak Masukan%', 'Pajak Dibayar Dimuka%']);
    }

    public static function salesAdvance(int $companyId): ?Account
    {
        return self::find($companyId, 'liability', ['Uang Muka Penjualan%', 'Uang Muka Pelanggan%']);
    }

    public static function purchaseAdvance(int $companyId): ?Account
    {
        return self::find($companyId, 'asset', ['Uang Muka Pembelian%', 'Uang Muka Pemasok%']);
    }

    /** Akun kontra pendapatan untuk retur penjualan. */
    public static function salesReturn(int $companyId): ?Account
    {
        return self::find($companyId, 'revenue', ['Retur Penjualan%']);
    }

    public static function depreciationExpense(int $companyId, ?string $code = null): ?Account
    {
        if ($code && ($a = Account::where('company_id', $companyId)->where('code', $code)->first())) return $a;
        return self::find($companyId, 'expense', ['Beban Penyusutan%', 'Biaya Penyusutan%', '%Penyusutan%']);
    }

    public static function accumulatedDepreciation(int $companyId, ?string $code = null): ?Account
    {
        if ($code && ($a = Account::where('company_id', $companyId)->where('code', $code)->first())) return $a;
        return self::find($companyId, 'asset', ['Akumulasi Penyusutan%']);
    }

    /** Akun ekuitas "Laba Tahun Berjalan" / Current Year Earnings (terhubung otomatis ke laba rugi tahun fiskal berjalan). */
    public static function currentYearEarnings(int $companyId): ?Account
    {
        return self::find($companyId, 'equity', ['Laba Tahun Berjalan%', 'Laba Berjalan%', 'Current Year Earnings%']);
    }

    /** Akun ekuitas "Laba Ditahan" (terhubung otomatis ke laba rugi tahun-tahun fiskal sebelumnya). */
    public static function retainedEarnings(int $companyId): ?Account
    {
        return self::find($companyId, 'equity', ['Laba Ditahan%', 'Retained Earnings%']);
    }
}
