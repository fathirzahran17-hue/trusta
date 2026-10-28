<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/** Konfirmasi pembayaran langganan dari klien. */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->query('tab') : 'pending';
        $q = SubscriptionPayment::with(['company', 'plan', 'reviewer'])->orderByDesc('id');
        if ($tab !== 'all') $q->where('status', $tab);
        $payments = $q->paginate(25)->withQueryString();
        $counts = SubscriptionPayment::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return view('superadmin.payments.index', compact('payments', 'tab', 'counts'));
    }

    public function approve(Request $request, SubscriptionPayment $payment)
    {
        $note = $request->validate(['admin_note' => 'nullable|string|max:255'])['admin_note'] ?? null;
        try {
            $sub = SubscriptionService::approve($payment, auth()->id(), $note);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran ' . $payment->company->name . ' disetujui. Langganan aktif s/d ' . $sub->end_date->format('d M Y') . '.');
    }

    public function reject(Request $request, SubscriptionPayment $payment)
    {
        $note = $request->validate(['admin_note' => 'required|string|max:255'], ['admin_note.required' => 'Tulis alasan penolakan agar klien tahu.'])['admin_note'];
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Pembayaran ini sudah diproses.');
        }
        $payment->update(['status' => 'rejected', 'admin_note' => $note, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        return back()->with('success', 'Pembayaran ditolak.');
    }

    public function proof(SubscriptionPayment $payment)
    {
        return \App\Http\Controllers\Client\BillingController::serveProof($payment);
    }
}
