<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Company::with(['users', 'subscriptions' => function($q) {
            $q->orderBy('end_date', 'desc')->with('plan');
        }])->orderBy('created_at', 'desc')->get();

        $plans = \App\Models\Plan::where('is_active', true)->get();

        return view('superadmin.clients.index', compact('clients', 'plans'));
    }

    public function updateSubscription(Request $request, Company $company)
    {
        $request->validate([
            'status' => 'required|in:trial,active,expired,cancelled',
            'end_date' => 'required|date',
        ]);

        // Cari subscription terakhir perusahaan ini
        $subscription = $company->subscriptions()->orderBy('end_date', 'desc')->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => $request->status,
                'end_date' => $request->end_date,
            ]);
            
            if ($request->status === 'expired' || $request->status === 'cancelled') {
                $company->update(['is_premium' => false]);
            } else {
                $company->update(['is_premium' => true]);
            }
        }

        return redirect()->route('superadmin.clients.index')->with('success', 'Status langganan berhasil diperbarui.');
    }

    public function grantPremium(Request $request, Company $company)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active',
            'end_date' => 'required|date',
        ]);

        // Nonaktifkan subscription lama jika ada
        $company->subscriptions()->update(['status' => 'cancelled']);

        // Buat subscription baru
        $company->subscriptions()->create([
            'plan_id' => $request->plan_id,
            'start_date' => now()->toDateString(),
            'end_date' => $request->end_date,
            'status' => $request->status,
        ]);

        $company->update(['is_premium' => true]);

        return redirect()->back()->with('success', "Premium berhasil diaktifkan untuk perusahaan {$company->name}.");
    }

    public function revokePremium(Company $company)
    {
        $company->update(['is_premium' => false]);
        $company->subscriptions()->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', "Premium berhasil dicabut untuk perusahaan {$company->name}.");
    }

    public function resetPassword(Request $request, Company $company)
    {
        $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        $mainUser = $company->users()->orderBy('id')->first();
        if ($mainUser) {
            $mainUser->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password)
            ]);
            return redirect()->back()->with('success', "Password untuk akun utama {$company->name} ({$mainUser->email}) berhasil direset.");
        }

        return redirect()->back()->with('error', "Gagal: Perusahaan ini belum memiliki akun pengguna yang terdaftar.");
    }

    public function destroy(Company $company)
    {
        $companyName = $company->name;

        // Hapus semua data terkait perusahaan ini secara berurutan
        // (dari yang paling detail ke yang paling umum, agar tidak ada foreign key conflict)

        // 1. Hapus detail jurnal terlebih dahulu
        $journalIds = $company->journals()->pluck('id');
        \App\Models\JournalDetail::whereIn('journal_id', $journalIds)->delete();
        $company->journals()->delete();

        // 2. Hapus item faktur penjualan
        $salesInvoiceIds = $company->salesInvoices()->pluck('id');
        \App\Models\SalesInvoiceItem::whereIn('sales_invoice_id', $salesInvoiceIds)->delete();
        $company->salesInvoices()->delete();

        // 3. Hapus item faktur pembelian
        $purchaseInvoiceIds = $company->purchaseInvoices()->pluck('id');
        \App\Models\PurchaseInvoiceItem::whereIn('purchase_invoice_id', $purchaseInvoiceIds)->delete();
        $company->purchaseInvoices()->delete();

        // 4. Hapus item sales quote (jika ada)
        if (method_exists($company, 'salesQuotes')) {
            $salesQuoteIds = $company->salesQuotes()->pluck('id');
            \App\Models\SalesQuoteItem::whereIn('sales_quote_id', $salesQuoteIds)->delete();
            $company->salesQuotes()->delete();
        }

        // 5. Hapus item purchase order (jika ada)
        if (method_exists($company, 'purchaseOrders')) {
            $purchaseOrderIds = $company->purchaseOrders()->pluck('id');
            \App\Models\PurchaseOrderItem::whereIn('purchase_order_id', $purchaseOrderIds)->delete();
            $company->purchaseOrders()->delete();
        }

        // 6. Hapus purchase returns (jika ada)
        if (method_exists($company, 'purchaseReturns')) {
            $company->purchaseReturns()->delete();
        }

        // 7. Hapus transaksi kas/bank
        $company->cashTransactions()->delete();

        // 8. Hapus data master
        $company->products()->delete();
        $company->customers()->delete();
        $company->suppliers()->delete();

        // 9. Hapus kategori produk
        \App\Models\ProductCategory::where('company_id', $company->id)->delete();

        // 10. Hapus akun perkiraan (CoA)
        $company->accounts()->delete();

        // 11. Hapus langganan
        $company->subscriptions()->delete();

        // 12. Hapus semua pengguna terkait perusahaan ini
        $company->users()->delete();

        // 13. Terakhir, hapus perusahaan itu sendiri
        $company->delete();

        return redirect()->route('superadmin.clients.index')->with('success', "Perusahaan \"{$companyName}\" beserta seluruh datanya telah berhasil dihapus.");
    }
}
