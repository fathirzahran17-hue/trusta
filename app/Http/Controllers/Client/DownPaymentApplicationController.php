<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DownPayment;
use App\Models\DownPaymentApplication;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\DownPaymentService;
use Illuminate\Http\Request;

class DownPaymentApplicationController extends Controller
{
    private function validated(Request $r): array
    {
        \App\Support\Money::normalize($r, ['amount']);

        return $r->validate([
            'down_payment_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'applied_date' => 'required|date',
        ]);
    }

    private function run(Request $request, $invoice, string $kind)
    {
        abort_if((int) $invoice->company_id !== (int) auth()->user()->company_id, 403);
        $d = $this->validated($request);
        $dp = DownPayment::where('company_id', $invoice->company_id)->find($d['down_payment_id']);
        if (! $dp) {
            return back()->withInput()->with('error', 'Uang muka tidak ditemukan.');
        }
        try {
            DownPaymentService::apply($dp, $invoice, $kind, (float) $d['amount'], $d['applied_date'], auth()->id());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Uang muka ' . $dp->payment_number . ' dipakai untuk melunasi faktur (Rp ' . number_format((float) $d['amount'], 2, ',', '.') . ').');
    }

    public function applySales(Request $request, SalesInvoice $sales_invoice) { return $this->run($request, $sales_invoice, 'sales'); }

    public function applyPurchase(Request $request, PurchaseInvoice $purchase_invoice) { return $this->run($request, $purchase_invoice, 'purchase'); }

    public function void(DownPaymentApplication $application)
    {
        abort_if((int) $application->company_id !== (int) auth()->user()->company_id, 403);
        try {
            DownPaymentService::void($application, auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pemakaian uang muka dibatalkan; faktur kembali memiliki sisa tagihan.');
    }
}
