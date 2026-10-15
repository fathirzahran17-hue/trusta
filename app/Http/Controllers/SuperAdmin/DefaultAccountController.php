<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DefaultAccount;
use Illuminate\Http\Request;

class DefaultAccountController extends Controller
{
    public function index()
    {
        $accounts = DefaultAccount::with('parent')
            ->orderBy('code')
            ->get();

        // Ambil header (parent) untuk dropdown
        $parentOptions = DefaultAccount::where('is_system', true)
            ->orderBy('code')
            ->get();

        return view('superadmin.default-accounts.index', compact('accounts', 'parentOptions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:default_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,cost_of_sales,expense,other_income,other_expense',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:default_accounts,id',
            'is_system' => 'boolean',
        ]);

        $maxSort = DefaultAccount::max('sort_order') ?? 0;

        DefaultAccount::create([
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'normal_balance' => $request->normal_balance,
            'parent_id' => $request->parent_id,
            'is_system' => $request->boolean('is_system'),
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('superadmin.default-accounts.index')
            ->with('success', "Akun default \"{$request->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, DefaultAccount $defaultAccount)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:default_accounts,code,' . $defaultAccount->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,cost_of_sales,expense,other_income,other_expense',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:default_accounts,id',
            'is_system' => 'boolean',
        ]);

        $defaultAccount->update([
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'normal_balance' => $request->normal_balance,
            'parent_id' => $request->parent_id,
            'is_system' => $request->boolean('is_system'),
        ]);

        return redirect()->route('superadmin.default-accounts.index')
            ->with('success', "Akun default \"{$request->name}\" berhasil diperbarui.");
    }

    public function destroy(DefaultAccount $defaultAccount)
    {
        $name = $defaultAccount->name;
        $defaultAccount->children()->delete();
        $defaultAccount->delete();

        return redirect()->route('superadmin.default-accounts.index')
            ->with('success', "Akun default \"{$name}\" beserta sub-akunnya berhasil dihapus.");
    }

    public function resetToDefault()
    {
        DefaultAccount::query()->delete();
        $this->seedDefaultTemplate();

        return redirect()->route('superadmin.default-accounts.index')
            ->with('success', 'Template akun default berhasil direset ke standar bawaan sistem.');
    }

    private function seedDefaultTemplate()
    {
        // ============================================================
        // 1. ASET (1-xxxx)
        // ============================================================
        $asset = DefaultAccount::create(['code' => '1-0000', 'name' => 'Aset', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true, 'sort_order' => 1]);

        // 1-1xxx Aset Lancar
        $asetLancar = DefaultAccount::create(['code' => '1-1000', 'name' => 'Aset Lancar', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asset->id, 'is_system' => true, 'sort_order' => 2]);
        $s = 3;
        foreach ([
            '1-1001' => 'Kas',
            '1-1002' => 'Bank Umum',
            '1-1003' => 'Piutang Dagang',
            '1-1004' => 'Piutang Lain-lain',
            '1-1005' => 'Uang Muka Pembelian',
            '1-1006' => 'Persediaan Barang Dagang',
            '1-1007' => 'Pajak Dibayar Dimuka',
            '1-1008' => 'Pajak Masukan',
            '1-1009' => 'Sewa Dibayar Dimuka',
            '1-1010' => 'Perlengkapan',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asetLancar->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // 1-2xxx Aset Tetap
        $asetTetap = DefaultAccount::create(['code' => '1-2000', 'name' => 'Aset Tetap', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $asset->id, 'is_system' => true, 'sort_order' => 20]);
        $s = 21;
        foreach ([
            '1-2001' => 'Tanah',
            '1-2002' => 'Bangunan',
            '1-2003' => 'Kendaraan',
            '1-2004' => 'Peralatan Kantor',
            '1-2005' => 'Mesin & Peralatan',
            '1-2006' => 'Akumulasi Penyusutan Bangunan',
            '1-2007' => 'Akumulasi Penyusutan Kendaraan',
            '1-2008' => 'Akumulasi Penyusutan Peralatan',
            '1-2009' => 'Akumulasi Penyusutan Mesin',
        ] as $code => $name) {
            // Akumulasi penyusutan saldo normalnya kredit (kontra aset)
            $balance = str_contains($name, 'Akumulasi') ? 'credit' : 'debit';
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'asset', 'normal_balance' => $balance, 'parent_id' => $asetTetap->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 2. KEWAJIBAN (2-xxxx)
        // ============================================================
        $liab = DefaultAccount::create(['code' => '2-0000', 'name' => 'Kewajiban', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true, 'sort_order' => 100]);

        // 2-1xxx Hutang Jangka Pendek
        $hutangPendek = DefaultAccount::create(['code' => '2-1000', 'name' => 'Hutang Jangka Pendek', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $liab->id, 'is_system' => true, 'sort_order' => 101]);
        $s = 102;
        foreach ([
            '2-1001' => 'Hutang Dagang',
            '2-1002' => 'Uang Muka Penjualan',
            '2-1003' => 'Hutang Biaya',
            '2-1004' => 'Hutang Gaji',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPendek->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // 2-11xx Hutang Pajak (sub dari Hutang Jangka Pendek)
        $hutangPajak = DefaultAccount::create(['code' => '2-1100', 'name' => 'Hutang Pajak', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPendek->id, 'is_system' => true, 'sort_order' => 110]);
        $s = 111;
        foreach ([
            '2-1101' => 'Hutang PPh 21',
            '2-1102' => 'Hutang PPh 23',
            '2-1103' => 'Hutang PPh 25',
            '2-1104' => 'Hutang PPh 29',
            '2-1105' => 'Hutang PPN',
            '2-1106' => 'Pajak Keluaran',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPajak->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // 2-2xxx Hutang Jangka Panjang
        $hutangPanjang = DefaultAccount::create(['code' => '2-2000', 'name' => 'Hutang Jangka Panjang', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $liab->id, 'is_system' => true, 'sort_order' => 120]);
        $s = 121;
        foreach ([
            '2-2001' => 'Hutang Bank',
            '2-2002' => 'Hutang Sewa Jangka Panjang',
            '2-2003' => 'Hutang Lain-lain Jangka Panjang',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => $hutangPanjang->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 3. EKUITAS (3-xxxx)
        // ============================================================
        $equity = DefaultAccount::create(['code' => '3-0000', 'name' => 'Ekuitas', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true, 'sort_order' => 200]);
        $s = 201;
        foreach ([
            '3-0001' => 'Retained Earnings',
            '3-0002' => 'Current Year Earnings',
            '3-0003' => 'Historical Balancing',
            '3-0004' => 'Laba Ditahan',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $equity->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        $modal = DefaultAccount::create(['code' => '3-1000', 'name' => 'Modal', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $equity->id, 'is_system' => true, 'sort_order' => 210]);
        $s = 211;
        foreach ([
            '3-1001' => 'Modal Pemilik',
            '3-1002' => 'Prive Pemilik',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => $modal->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 4. PENDAPATAN (4-xxxx)
        // ============================================================
        $income = DefaultAccount::create(['code' => '4-0000', 'name' => 'Pendapatan', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'sort_order' => 300]);
        $s = 301;
        foreach ([
            '4-0001' => 'Pendapatan Penjualan',
            '4-0002' => 'Pendapatan Jasa',
            '4-0003' => 'Diskon Penjualan',
            '4-0004' => 'Retur Penjualan',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => $income->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 5. HARGA POKOK PENJUALAN (5-xxxx)
        // ============================================================
        $cos = DefaultAccount::create(['code' => '5-0000', 'name' => 'Harga Pokok Penjualan', 'type' => 'cost_of_sales', 'normal_balance' => 'debit', 'is_system' => true, 'sort_order' => 400]);
        $s = 401;
        foreach ([
            '5-0001' => 'HPP - Bahan Baku / Barang Dagang',
            '5-0002' => 'HPP - Tenaga Kerja Langsung',
            '5-0003' => 'HPP - Overhead',
            '5-0004' => 'Biaya Pengiriman Barang',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'cost_of_sales', 'normal_balance' => 'debit', 'parent_id' => $cos->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 6. BEBAN USAHA (6-xxxx)
        // ============================================================
        $expense = DefaultAccount::create(['code' => '6-0000', 'name' => 'Beban Usaha', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'sort_order' => 500]);

        $biayaKaryawan = DefaultAccount::create(['code' => '6-1000', 'name' => 'Biaya Karyawan', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $expense->id, 'is_system' => true, 'sort_order' => 501]);
        $s = 502;
        foreach ([
            '6-1001' => 'Gaji Karyawan',
            '6-1002' => 'THR Karyawan',
            '6-1003' => 'Tunjangan Karyawan',
            '6-1004' => 'BPJS Ketenagakerjaan',
            '6-1005' => 'BPJS Kesehatan',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $biayaKaryawan->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        $biayaAdm = DefaultAccount::create(['code' => '6-2000', 'name' => 'Biaya Administrasi & Umum', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $expense->id, 'is_system' => true, 'sort_order' => 510]);
        $s = 511;
        foreach ([
            '6-2001' => 'Biaya Listrik, Air & Telepon',
            '6-2002' => 'Biaya Sewa',
            '6-2003' => 'Biaya Perlengkapan Kantor',
            '6-2004' => 'Biaya Transportasi',
            '6-2005' => 'Biaya Penyusutan',
            '6-2006' => 'Biaya Asuransi',
            '6-2007' => 'Biaya Pemeliharaan',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => $biayaAdm->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 8. PENDAPATAN LAIN-LAIN (8-xxxx)
        // ============================================================
        $otherIncome = DefaultAccount::create(['code' => '8-0000', 'name' => 'Pendapatan Lain-lain', 'type' => 'other_income', 'normal_balance' => 'credit', 'is_system' => true, 'sort_order' => 600]);
        $s = 601;
        foreach ([
            '8-0001' => 'Pendapatan Bunga Bank',
            '8-0002' => 'Laba Selisih Kurs',
            '8-0003' => 'Pendapatan Lain-lain',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'other_income', 'normal_balance' => 'credit', 'parent_id' => $otherIncome->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        // ============================================================
        // 9. BEBAN LAIN-LAIN (9-xxxx)
        // ============================================================
        $otherExpense = DefaultAccount::create(['code' => '9-0000', 'name' => 'Beban Lain-lain', 'type' => 'other_expense', 'normal_balance' => 'debit', 'is_system' => true, 'sort_order' => 700]);
        $s = 701;
        foreach ([
            '9-0001' => 'Biaya Administrasi Bank',
            '9-0002' => 'Rugi Selisih Kurs',
            '9-0003' => 'Beban Lain-lain',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $otherExpense->id, 'is_system' => false, 'sort_order' => $s++]);
        }

        $biayaPajak = DefaultAccount::create(['code' => '9-1000', 'name' => 'Biaya Pajak', 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $otherExpense->id, 'is_system' => true, 'sort_order' => 710]);
        $s = 711;
        foreach ([
            '9-1001' => 'Biaya Pajak PPh 21',
            '9-1002' => 'Biaya Pajak PPh 23',
            '9-1003' => 'Biaya Pajak PPh 25',
            '9-1004' => 'Biaya Pajak PPh 29',
        ] as $code => $name) {
            DefaultAccount::create(['code' => $code, 'name' => $name, 'type' => 'other_expense', 'normal_balance' => 'debit', 'parent_id' => $biayaPajak->id, 'is_system' => false, 'sort_order' => $s++]);
        }
    }
}
