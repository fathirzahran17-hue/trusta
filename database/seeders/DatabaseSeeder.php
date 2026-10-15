<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Super Admin
        User::create([
            'name' => 'Fathir (Super Admin)',
            'email' => 'superadmin@trusta.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // 2. Create Plan
        $plan = Plan::create([
            'name' => 'Pro Business',
            'price' => 500000,
            'max_accounts' => 1000,
            'max_transactions' => 10000,
            'max_users' => 10,
            'has_reports' => true,
            'has_cash_bank' => true,
            'has_export' => true,
            'has_auto_backup' => true,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        // 3. Create Dummy Company (Tenant)
        $company = Company::create([
            'name' => 'PT Makmur Sejahtera',
            'email' => 'contact@makmursejahtera.com',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 1, Jakarta',
            'fiscal_year_start' => 1, // Januari
            'is_active' => true,
        ]);

        // 4. Create Active Subscription for Company
        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'amount_paid' => 5000000,
            'notes' => 'Pembayaran awal tahun',
        ]);

        // 5. Create Client Admin
        User::create([
            'company_id' => $company->id,
            'name' => 'Budi (Client Admin)',
            'email' => 'client@makmur.com',
            'password' => Hash::make('password'),
            'role' => 'client_admin',
            'is_active' => true,
        ]);

        // 6. Generate Standard COA for Company
        $standardAccounts = [
            // ASSETS
            ['code' => '1-10001', 'name' => 'Kas Tunai', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1-10002', 'name' => 'Bank BCA', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '1-20001', 'name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1-30001', 'name' => 'Persediaan Barang Dagang', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1-40001', 'name' => 'Aset Tetap (Inventaris)', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '1-40002', 'name' => 'Akumulasi Penyusutan Aset', 'type' => 'asset', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '1-50001', 'name' => 'Uang Muka Pembelian', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1-60001', 'name' => 'PPN Masukan', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
            
            // LIABILITIES
            ['code' => '2-10001', 'name' => 'Hutang Usaha', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '2-20001', 'name' => 'Hutang Pajak', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => false],
            ['code' => '2-20002', 'name' => 'PPN Keluaran', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '2-30001', 'name' => 'Hutang Bank', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => false],
            ['code' => '2-40001', 'name' => 'Uang Muka Penjualan', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
            
            // EQUITY
            ['code' => '3-10001', 'name' => 'Modal Pemilik', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '3-20001', 'name' => 'Laba Ditahan', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '3-30001', 'name' => 'Laba Periode Berjalan', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '3-99999', 'name' => 'Ekuitas Saldo Awal', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],

            // REVENUE
            ['code' => '4-10001', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '4-20001', 'name' => 'Pendapatan Jasa', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => false],
            ['code' => '4-90001', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => false],

            // EXPENSE
            ['code' => '5-10001', 'name' => 'Harga Pokok Penjualan (HPP)', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5-20001', 'name' => 'Beban Gaji & Upah', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '5-20002', 'name' => 'Beban Listrik, Air & Telepon', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '5-20003', 'name' => 'Beban Sewa', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '5-20004', 'name' => 'Beban Penyusutan Aset', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5-90001', 'name' => 'Beban Lain-lain', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => false],
        ];

        foreach ($standardAccounts as $acc) {
            \App\Models\Account::create(array_merge($acc, [
                'company_id' => $company->id,
                'opening_balance' => 0,
                'is_active' => true,
            ]));
        }
    }
}
