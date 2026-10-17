<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\InvoicePaymentService;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    private function validated(Request $request): array
    {
        return $request->validate([
            'account_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);
    }

    public function storeSales(Request $request, SalesInvoice $sales_invoice)
    {
        abort_if($sales_invoice->company_id !== auth()->user()->company_id, 403);
        $d = $this->validated($request);
        try {
            InvoicePaymentService::paySales($sales_invoice, (int) $d['account_id'], (float) $d['amount'], $d['payment_date'], $d['notes'] ?? null, auth()->id());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran berhasil dicatat dan jurnal dibuat.');
    }

    public function storePurchase(Request $request, PurchaseInvoice $purchase_invoice)
    {
        abort_if($purchase_invoice->company_id !== auth()->user()->company_id, 403);
        $d = $this->validated($request);
        try {
            InvoicePaymentService::payPurchase($purchase_invoice, (int) $d['account_id'], (float) $d['amount'], $d['payment_date'], $d['notes'] ?? null, auth()->id());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran berhasil dicatat dan jurnal dibuat.');
    }

    public function voidSales(SalesInvoice $sales_invoice)
    {
        abort_if($sales_invoice->company_id !== auth()->user()->company_id, 403);
        try {
            InvoicePaymentService::void($sales_invoice, 'sales', auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Faktur dibatalkan dan jurnal balik dibuat.');
    }

    public function voidPurchase(PurchaseInvoice $purchase_invoice)
    {
        abort_if($purchase_invoice->company_id !== auth()->user()->company_id, 403);
        try {
            InvoicePaymentService::void($purchase_invoice, 'purchase', auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Faktur dibatalkan dan jurnal balik dibuat.');
    }
}
