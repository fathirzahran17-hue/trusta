<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DefaultAccount;

class CoaSeeder
{
    public static function seed(int $companyId)
    {
        // Cek apakah tabel default_accounts ada dan ada datanya
        try {
            $defaults = DefaultAccount::orderBy('sort_order')->orderBy('code')->get();
        } catch (\Exception $e) {
            $defaults = collect();
        }

        if ($defaults->isNotEmpty()) {
            self::seedFromDatabase($companyId, $defaults);
        } else {
            self::seedHardcoded($companyId);
        }
    }

    /**
     * Buat akun berdasarkan template dari tabel default_accounts
     * FIX: Gunakan explicit (bool) cast untuk is_system
     */
    private static function seedFromDatabase(int $companyId, $defaults)
    {
        $idMap = [];

        foreach ($defaults as $default) {
            $parentId = null;
            if ($default->parent_id && isset($idMap[$default->parent_id])) {
                $parentId = $idMap[$default->parent_id];
            }

            $newAccount = Account::create([
                'company_id' => $companyId,
                'code' => $default->code,
                'name' => $default->name,
                'type' => $default->type,
                'normal_balance' => $default->normal_balance,
                'parent_id' => $parentId,
                'is_system' => (bool) $default->is_system,
            ]);

            $idMap[$default->id] = $newAccount->id;
        }
    }

    /**
     * Template hardcoded (fallback jika tabel default_accounts kosong)
     * Sudah dilengkapi: Aset Tetap, Hutang Jangka Panjang, dll
     */
    private static function seedHardcoded(int $companyId)
    {
        // ===== 1. ASET =====
        $asset = Account::create(['company_id' => $companyId, 'code' => '1-0000', 'name' => 'Aset', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true]);

        // Aset Lancar
        $asetLancar = Account::create(['company_id' => $companyId, 'code' => '1-1000', 'name' => 'Aset Lancar', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asset->id, 'is_system' => true]);
        foreach ([
            '1-1001' => 'Kas', '1-1002' => 'Bank Umum', '1-1003' => 'Piutang Dagang',
            '1-1004' => 'Piutang Lain-lain', '1-1005' => 'Uang Muka Pembelian',
            '1-1006' => 'Persediaan Barang Dagang', '1-1007' => 'Pajak Dibayar Dimuka',
            '1-1008' => 'Pajak Masukan', '1-1009' => 'Sewa Dibayar Dimuka', '1-1010' => 'Perlengkapan',
        ] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asetLancar->id, 'is_system' => false]);
        }

        // Aset Tetap
        $asetTetap = Account::create(['company_id' => $companyId, 'code' => '1-2000', 'name' => 'Aset Tetap', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asset->id, 'is_system' => true]);
        foreach ([
            '1-2001' => ['Tanah', 'debit'], '1-2002' => ['Bangunan', 'debit'],
            '1-2003' => ['Kendaraan', 'debit'], '1-2004' => ['Peralatan Kantor', 'debit'],
            '1-2005' => ['Mesin & Peralatan', 'debit'],
            '1-2006' => ['Akumulasi Penyusutan Bangunan', 'credit'],
            '1-2007' => ['Akumulasi Penyusutan Kendaraan', 'credit'],
            '1-2008' => ['Akumulasi Penyusutan Peralatan', 'credit'],
            '1-2009' => ['Akumulasi Penyusutan Mesin', 'credit'],
        ] as $code => [$name, $bal]) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'asset', 'normal_balance' => $bal, 'parent_id' => $asetTetap->id, 'is_system' => false]);
        }

        // ===== 2. KEWAJIBAN =====
        $liab = Account::create(['company_id' => $companyId, 'code' => '2-0000', 'name' => 'Kewajiban', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true]);

        // Hutang Jangka Pendek
        $hutangPendek = Account::create(['company_id' => $companyId, 'code' => '2-1000', 'name' => 'Hutang Jangka Pendek', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $liab->id, 'is_system' => true]);
        foreach (['2-1001' => 'Hutang Dagang', '2-1002' => 'Uang Muka Penjualan', '2-1003' => 'Hutang Biaya', '2-1004' => 'Hutang Gaji'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPendek->id, 'is_system' => false]);
        }

        $hutangPajak = Account::create(['company_id' => $companyId, 'code' => '2-1100', 'name' => 'Hutang Pajak', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPendek->id, 'is_system' => true]);
        foreach (['2-1101' => 'Hutang PPh 21', '2-1102' => 'Hutang PPh 23', '2-1103' => 'Hutang PPh 25', '2-1104' => 'Hutang PPh 29', '2-1105' => 'Hutang PPN', '2-1106' => 'Pajak Keluaran'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPajak->id, 'is_system' => false]);
        }

        // Hutang Jangka Panjang
        $hutangPanjang = Account::create(['company_id' => $companyId, 'code' => '2-2000', 'name' => 'Hutang Jangka Panjang', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $liab->id, 'is_system' => true]);
        foreach (['2-2001' => 'Hutang Bank', '2-2002' => 'Hutang Sewa Jangka Panjang', '2-2003' => 'Hutang Lain-lain Jangka Panjang'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPanjang->id, 'is_system' => false]);
        }

        // ===== 3. EKUITAS =====
        $equity = Account::create(['company_id' => $companyId, 'code' => '3-0000', 'name' => 'Ekuitas', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true]);
        foreach (['3-0001' => 'Retained Earnings', '3-0002' => 'Current Year Earnings', '3-0003' => 'Historical Balancing', '3-0004' => 'Laba Ditahan'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $equity->id, 'is_system' => false]);
        }

        $modal = Account::create(['company_id' => $companyId, 'code' => '3-1000', 'name' => 'Modal', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $equity->id, 'is_system' => true]);
        foreach (['3-1001' => 'Modal Pemilik', '3-1002' => 'Prive Pemilik'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $modal->id, 'is_system' => false]);
        }

        // ===== 4. PENDAPATAN =====
        $income = Account::create(['company_id' => $companyId, 'code' => '4-0000', 'name' => 'Pendapatan', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true]);
        foreach (['4-0001' => 'Pendapatan Penjualan', '4-0002' => 'Pendapatan Jasa', '4-0003' => 'Diskon Penjualan', '4-0004' => 'Retur Penjualan'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => $income->id, 'is_system' => false]);
        }

        // ===== 5. HPP =====
        $cos = Account::create(['company_id' => $companyId, 'code' => '5-0000', 'name' => 'Harga Pokok Penjualan', 'type' => 'cost_of_sales', 'normal_balance' => 'debit', 'is_system' => true]);
        foreach (['5-0001' => 'HPP - Bahan Baku / Barang Dagang', '5-0002' => 'HPP - Tenaga Kerja Langsung', '5-0003' => 'HPP - Overhead', '5-0004' => 'Biaya Pengiriman Barang'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'cost_of_sales', 'normal_balance' => 'debit', 'parent_id' => $cos->id, 'is_system' => false]);
        }

        // ===== 6. BEBAN USAHA =====
        $expense = Account::create(['company_id' => $companyId, 'code' => '6-0000', 'name' => 'Beban Usaha', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true]);

        $biayaKaryawan = Account::create(['company_id' => $companyId, 'code' => '6-1000', 'name' => 'Biaya Karyawan', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $expense->id, 'is_system' => true]);
        foreach (['6-1001' => 'Gaji Karyawan', '6-1002' => 'THR Karyawan', '6-1003' => 'Tunjangan Karyawan', '6-1004' => 'BPJS Ketenagakerjaan', '6-1005' => 'BPJS Kesehatan'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $biayaKaryawan->id, 'is_system' => false]);
        }

        $biayaAdm = Account::create(['company_id' => $companyId, 'code' => '6-2000', 'name' => 'Biaya Administrasi & Umum', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $expense->id, 'is_system' => true]);
        foreach (['6-2001' => 'Biaya Listrik, Air & Telepon', '6-2002' => 'Biaya Sewa', '6-2003' => 'Biaya Perlengkapan Kantor', '6-2004' => 'Biaya Transportasi', '6-2005' => 'Biaya Penyusutan', '6-2006' => 'Biaya Asuransi', '6-2007' => 'Biaya Pemeliharaan'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $biayaAdm->id, 'is_system' => false]);
        }

        // ===== 8. PENDAPATAN LAIN-LAIN =====
        $otherIncome = Account::create(['company_id' => $companyId, 'code' => '8-0000', 'name' => 'Pendapatan Lain-lain', 'type' => 'other_income', 'normal_balance' => 'credit', 'is_system' => true]);
        foreach (['8-0001' => 'Pendapatan Bunga Bank', '8-0002' => 'Laba Selisih Kurs', '8-0003' => 'Pendapatan Lain-lain'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'other_income', 'normal_balance' => 'credit', 'parent_id' => $otherIncome->id, 'is_system' => false]);
        }

        // ===== 9. BEBAN LAIN-LAIN =====
        $otherExpense = Account::create(['company_id' => $companyId, 'code' => '9-0000', 'name' => 'Beban Lain-lain', 'type' => 'other_expense', 'normal_balance' => 'debit', 'is_system' => true]);
        foreach (['9-0001' => 'Biaya Administrasi Bank', '9-0002' => 'Rugi Selisih Kurs', '9-0003' => 'Beban Lain-lain'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $otherExpense->id, 'is_system' => false]);
        }

        $biayaPajak = Account::create(['company_id' => $companyId, 'code' => '9-1000', 'name' => 'Biaya Pajak', 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $otherExpense->id, 'is_system' => true]);
        foreach (['9-1001' => 'Biaya Pajak PPh 21', '9-1002' => 'Biaya Pajak PPh 23', '9-1003' => 'Biaya Pajak PPh 25', '9-1004' => 'Biaya Pajak PPh 29'] as $code => $name) {
            Account::create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $biayaPajak->id, 'is_system' => false]);
        }
    }
}
