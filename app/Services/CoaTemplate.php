<?php

namespace App\Services;

use App\Models\DefaultAccount;

/**
 * Bagan akun (CoA) standar TRUSTA untuk UMKM Indonesia (berbasis SAK EMKM / SAK ETAP).
 * Satu template induk; tiap akun ditandai untuk bidang usaha: jasa, dagang, manufaktur (kosong = semua).
 * Nama akun sengaja mengikuti pola yang dipakai jurnal otomatis (AccountResolver) — jangan diubah sembarangan.
 */
class CoaTemplate
{
    public const INDUSTRIES = ['jasa' => 'Jasa', 'dagang' => 'Dagang', 'manufaktur' => 'Manufaktur'];

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:?string,5:bool,6:string}> [kode, nama, tipe, saldo normal, kode induk, header?, industri] */
    public static function standard(): array
    {
        $A = '';                       // semua bidang
        $J = 'jasa'; $D = 'dagang'; $M = 'manufaktur';
        $DM = 'dagang,manufaktur';

        return [
            // ===== 1. ASET =====
            ['1-0000', 'Aset', 'asset', 'debit', null, true, $A],
            ['1-1000', 'Aset Lancar', 'asset', 'debit', '1-0000', true, $A],
            ['1-1100', 'Kas & Setara Kas', 'asset', 'debit', '1-1000', true, $A],
            ['1-1101', 'Kas', 'asset', 'debit', '1-1100', false, $A],
            ['1-1102', 'Kas Kecil', 'asset', 'debit', '1-1100', false, $A],
            ['1-1110', 'Bank', 'asset', 'debit', '1-1100', false, $A],
            ['1-1200', 'Piutang', 'asset', 'debit', '1-1000', true, $A],
            ['1-1201', 'Piutang Usaha', 'asset', 'debit', '1-1200', false, $A],
            ['1-1202', 'Piutang Karyawan', 'asset', 'debit', '1-1200', false, $A],
            ['1-1203', 'Piutang Lain-lain', 'asset', 'debit', '1-1200', false, $A],
            ['1-1209', 'Cadangan Kerugian Piutang', 'asset', 'credit', '1-1200', false, $A],
            ['1-1300', 'Persediaan', 'asset', 'debit', '1-1000', true, $DM],
            ['1-1301', 'Persediaan Barang Dagang', 'asset', 'debit', '1-1300', false, $D],
            ['1-1302', 'Persediaan Bahan Baku', 'asset', 'debit', '1-1300', false, $M],
            ['1-1303', 'Persediaan Bahan Penolong', 'asset', 'debit', '1-1300', false, $M],
            ['1-1304', 'Persediaan Barang Dalam Proses', 'asset', 'debit', '1-1300', false, $M],
            ['1-1305', 'Persediaan Barang Jadi', 'asset', 'debit', '1-1300', false, $M],
            ['1-1400', 'Uang Muka & Biaya Dibayar Dimuka', 'asset', 'debit', '1-1000', true, $A],
            ['1-1401', 'Uang Muka Pembelian', 'asset', 'debit', '1-1400', false, $A],
            ['1-1402', 'Sewa Dibayar Dimuka', 'asset', 'debit', '1-1400', false, $A],
            ['1-1403', 'Asuransi Dibayar Dimuka', 'asset', 'debit', '1-1400', false, $A],
            ['1-1404', 'Perlengkapan', 'asset', 'debit', '1-1400', false, $A],
            ['1-1500', 'Pajak Dibayar Dimuka', 'asset', 'debit', '1-1000', true, $A],
            ['1-1501', 'Pajak Masukan (PPN)', 'asset', 'debit', '1-1500', false, $A],
            ['1-1502', 'PPh 22 Dibayar Dimuka', 'asset', 'debit', '1-1500', false, $DM],
            ['1-1503', 'PPh 23 Dibayar Dimuka', 'asset', 'debit', '1-1500', false, $A],
            ['1-1504', 'PPh 25 Dibayar Dimuka', 'asset', 'debit', '1-1500', false, $A],
            ['1-1505', 'PPh Final 4(2) Dibayar Dimuka', 'asset', 'debit', '1-1500', false, $A],
            ['1-2000', 'Aset Tetap', 'asset', 'debit', '1-0000', true, $A],
            ['1-2001', 'Tanah', 'asset', 'debit', '1-2000', false, $A],
            ['1-2002', 'Bangunan', 'asset', 'debit', '1-2000', false, $A],
            ['1-2003', 'Kendaraan', 'asset', 'debit', '1-2000', false, $A],
            ['1-2004', 'Peralatan & Inventaris Kantor', 'asset', 'debit', '1-2000', false, $A],
            ['1-2005', 'Mesin & Peralatan Produksi', 'asset', 'debit', '1-2000', false, $M],
            ['1-2101', 'Akumulasi Penyusutan Bangunan', 'asset', 'credit', '1-2000', false, $A],
            ['1-2102', 'Akumulasi Penyusutan Kendaraan', 'asset', 'credit', '1-2000', false, $A],
            ['1-2103', 'Akumulasi Penyusutan Peralatan Kantor', 'asset', 'credit', '1-2000', false, $A],
            ['1-2104', 'Akumulasi Penyusutan Mesin', 'asset', 'credit', '1-2000', false, $M],
            ['1-3000', 'Aset Lain-lain', 'asset', 'debit', '1-0000', true, $A],
            ['1-3001', 'Aset Tak Berwujud', 'asset', 'debit', '1-3000', false, $A],
            ['1-3002', 'Uang Jaminan', 'asset', 'debit', '1-3000', false, $A],

            // ===== 2. KEWAJIBAN =====
            ['2-0000', 'Kewajiban', 'liability', 'credit', null, true, $A],
            ['2-1000', 'Kewajiban Jangka Pendek', 'liability', 'credit', '2-0000', true, $A],
            ['2-1001', 'Hutang Usaha', 'liability', 'credit', '2-1000', false, $A],
            ['2-1002', 'Hutang Lain-lain', 'liability', 'credit', '2-1000', false, $A],
            ['2-1003', 'Uang Muka Penjualan', 'liability', 'credit', '2-1000', false, $A],
            ['2-1004', 'Pendapatan Diterima Dimuka', 'liability', 'credit', '2-1000', false, $J],
            ['2-1005', 'Hutang Gaji & Upah', 'liability', 'credit', '2-1000', false, $A],
            ['2-1006', 'Beban Masih Harus Dibayar', 'liability', 'credit', '2-1000', false, $A],
            ['2-1100', 'Hutang Pajak', 'liability', 'credit', '2-1000', true, $A],
            ['2-1101', 'Pajak Keluaran (PPN)', 'liability', 'credit', '2-1100', false, $A],
            ['2-1102', 'Hutang PPh 21', 'liability', 'credit', '2-1100', false, $A],
            ['2-1103', 'Hutang PPh 23', 'liability', 'credit', '2-1100', false, $A],
            ['2-1104', 'Hutang PPh Final 4(2)', 'liability', 'credit', '2-1100', false, $A],
            ['2-1105', 'Hutang PPh 25', 'liability', 'credit', '2-1100', false, $A],
            ['2-1106', 'Hutang PPh 29', 'liability', 'credit', '2-1100', false, $A],
            ['2-2000', 'Kewajiban Jangka Panjang', 'liability', 'credit', '2-0000', true, $A],
            ['2-2001', 'Hutang Bank', 'liability', 'credit', '2-2000', false, $A],
            ['2-2002', 'Hutang Leasing', 'liability', 'credit', '2-2000', false, $A],
            ['2-2003', 'Hutang Pemegang Saham', 'liability', 'credit', '2-2000', false, $A],

            // ===== 3. EKUITAS =====
            ['3-0000', 'Ekuitas', 'equity', 'credit', null, true, $A],
            ['3-1001', 'Modal Disetor', 'equity', 'credit', '3-0000', false, $A],
            ['3-1002', 'Prive Pemilik', 'equity', 'debit', '3-0000', false, $A],
            ['3-2001', 'Laba Ditahan', 'equity', 'credit', '3-0000', false, $A],
            ['3-2002', 'Laba Tahun Berjalan', 'equity', 'credit', '3-0000', false, $A],
            ['3-9999', 'Ekuitas Saldo Awal', 'equity', 'credit', '3-0000', false, $A],

            // ===== 4. PENDAPATAN =====
            ['4-0000', 'Pendapatan', 'revenue', 'credit', null, true, $A],
            ['4-1001', 'Pendapatan Penjualan', 'revenue', 'credit', '4-0000', false, $DM],
            ['4-1002', 'Pendapatan Jasa', 'revenue', 'credit', '4-0000', false, $J],
            ['4-1003', 'Retur Penjualan', 'revenue', 'credit', '4-0000', false, $DM],
            ['4-1004', 'Potongan Penjualan', 'revenue', 'credit', '4-0000', false, $A],

            // ===== 5. HARGA POKOK =====
            ['5-0000', 'Harga Pokok Penjualan', 'cost_of_sales', 'debit', null, true, $A],
            ['5-1001', 'HPP - Barang Dagang', 'cost_of_sales', 'debit', '5-0000', false, $D],
            ['5-1002', 'Biaya Angkut Pembelian', 'cost_of_sales', 'debit', '5-0000', false, $DM],
            ['5-1003', 'Potongan Pembelian', 'cost_of_sales', 'credit', '5-0000', false, $DM],
            ['5-2001', 'HPP - Barang Jadi', 'cost_of_sales', 'debit', '5-0000', false, $M],
            ['5-2002', 'Biaya Tenaga Kerja Langsung', 'cost_of_sales', 'debit', '5-0000', false, $M],
            ['5-2003', 'Biaya Overhead Pabrik', 'cost_of_sales', 'debit', '5-0000', false, $M],
            ['5-2004', 'Biaya Tenaga Kerja Dibebankan ke Produksi', 'cost_of_sales', 'credit', '5-0000', false, $M],
            ['5-2005', 'Overhead Pabrik Dibebankan ke Produksi', 'cost_of_sales', 'credit', '5-0000', false, $M],
            ['5-3001', 'Beban Pokok Pendapatan Jasa', 'cost_of_sales', 'debit', '5-0000', false, $J],
            ['5-3002', 'HPP - Jasa Subkontrak', 'cost_of_sales', 'debit', '5-0000', false, $J],

            // ===== 6. BEBAN OPERASIONAL =====
            ['6-0000', 'Beban Operasional', 'expense', 'debit', null, true, $A],
            ['6-1000', 'Beban Penjualan & Pemasaran', 'expense', 'debit', '6-0000', true, $A],
            ['6-1001', 'Beban Iklan & Promosi', 'expense', 'debit', '6-1000', false, $A],
            ['6-1002', 'Beban Pengiriman Penjualan', 'expense', 'debit', '6-1000', false, $DM],
            ['6-1003', 'Beban Komisi Penjualan', 'expense', 'debit', '6-1000', false, $A],
            ['6-2000', 'Beban Umum & Administrasi', 'expense', 'debit', '6-0000', true, $A],
            ['6-2001', 'Beban Gaji & Upah', 'expense', 'debit', '6-2000', false, $A],
            ['6-2002', 'Beban Tunjangan & THR', 'expense', 'debit', '6-2000', false, $A],
            ['6-2003', 'Beban BPJS', 'expense', 'debit', '6-2000', false, $A],
            ['6-2004', 'Beban Sewa', 'expense', 'debit', '6-2000', false, $A],
            ['6-2005', 'Beban Listrik, Air & Telepon', 'expense', 'debit', '6-2000', false, $A],
            ['6-2006', 'Beban Internet & Perangkat Lunak', 'expense', 'debit', '6-2000', false, $A],
            ['6-2007', 'Beban Perlengkapan Kantor', 'expense', 'debit', '6-2000', false, $A],
            ['6-2008', 'Beban Transportasi & Perjalanan Dinas', 'expense', 'debit', '6-2000', false, $A],
            ['6-2009', 'Beban Pemeliharaan & Perbaikan', 'expense', 'debit', '6-2000', false, $A],
            ['6-2010', 'Beban Asuransi', 'expense', 'debit', '6-2000', false, $A],
            ['6-2011', 'Beban Penyusutan', 'expense', 'debit', '6-2000', false, $A],
            ['6-2012', 'Beban Jasa Profesional', 'expense', 'debit', '6-2000', false, $A],
            ['6-2013', 'Beban Kerugian Piutang', 'expense', 'debit', '6-2000', false, $A],
            ['6-2014', 'Beban Selisih Persediaan', 'expense', 'debit', '6-2000', false, $DM],
            ['6-2015', 'Beban Perizinan & Legal', 'expense', 'debit', '6-2000', false, $A],
            ['6-2099', 'Beban Umum Lain-lain', 'expense', 'debit', '6-2000', false, $A],

            // ===== 8. PENDAPATAN LAIN-LAIN =====
            ['8-0000', 'Pendapatan Lain-lain', 'other_income', 'credit', null, true, $A],
            ['8-0001', 'Pendapatan Bunga Bank', 'other_income', 'credit', '8-0000', false, $A],
            ['8-0002', 'Laba Selisih Kurs', 'other_income', 'credit', '8-0000', false, $A],
            ['8-0003', 'Laba Pelepasan Aset Tetap', 'other_income', 'credit', '8-0000', false, $A],
            ['8-0099', 'Pendapatan Non-Operasional Lainnya', 'other_income', 'credit', '8-0000', false, $A],

            // ===== 9. BEBAN LAIN-LAIN =====
            ['9-0000', 'Beban Lain-lain', 'other_expense', 'debit', null, true, $A],
            ['9-0001', 'Beban Administrasi Bank', 'other_expense', 'debit', '9-0000', false, $A],
            ['9-0002', 'Rugi Selisih Kurs', 'other_expense', 'debit', '9-0000', false, $A],
            ['9-0003', 'Rugi Pelepasan Aset Tetap', 'other_expense', 'debit', '9-0000', false, $A],
            ['9-0004', 'Beban Bunga Pinjaman', 'other_expense', 'debit', '9-0000', false, $A],
            ['9-0099', 'Beban Non-Operasional Lainnya', 'other_expense', 'debit', '9-0000', false, $A],
            ['9-1000', 'Beban Pajak Penghasilan', 'other_expense', 'debit', '9-0000', true, $A],
            ['9-1001', 'Beban PPh Badan', 'other_expense', 'debit', '9-1000', false, $A],
            ['9-1002', 'Beban Pajak Final', 'other_expense', 'debit', '9-1000', false, $A],
        ];
    }

    /** Isi ulang tabel template (default_accounts) dengan CoA standar. */
    public static function install(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            DefaultAccount::query()->update(['parent_id' => null]);
            DefaultAccount::query()->delete();
            $ids = [];
            foreach (self::standard() as $i => [$code, $name, $type, $bal, $parent, $header, $ind]) {
                $ids[$code] = DefaultAccount::create([
                    'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $bal,
                    'parent_id' => $parent ? ($ids[$parent] ?? null) : null,
                    'is_system' => $header, 'sort_order' => $i + 1, 'industries' => $ind ?: null,
                ])->id;
            }
        });
    }

    /** Apakah akun template berlaku untuk salah satu industri? (kosong = semua) */
    public static function applies(?string $industries, array $wanted): bool
    {
        $list = array_filter(array_map('trim', explode(',', (string) $industries)));

        return empty($list) || (bool) array_intersect($list, $wanted);
    }
}
