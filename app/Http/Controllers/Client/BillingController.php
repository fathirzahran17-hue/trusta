<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/** Halaman Langganan klien: status, pilih paket, kirim bukti transfer, riwayat pembayaran. */
class BillingController extends Controller
{
    public function index()
    {
        $company = auth()->user()->company;
        $current = SubscriptionService::current($company);
        $plans = Plan::where('is_active', true)->where('is_public', true)->orderBy('sort_order')->orderBy('price')->get();
        $payments = SubscriptionPayment::with('plan')->where('company_id', $company->id)->orderByDesc('id')->limit(20)->get();
        $history = $company->subscriptions()->with('plan')->orderByDesc('end_date')->limit(10)->get();
        $bank = AppSetting::get('bank_accounts');
        $wa = AppSetting::get('admin_whatsapp');
        $note = AppSetting::get('billing_note');

        return view('client.billing.index', compact('company', 'current', 'plans', 'payments', 'history', 'bank', 'wa', 'note'));
    }

    public function store(Request $request)
    {
        $company = auth()->user()->company;
        $v = $request->validate([
            'plan_id' => 'required|integer',
            'billing_cycle' => 'required|in:monthly,yearly',
            'months' => 'nullable|integer|min:1|max:12',
            'payer_name' => 'required|string|max:120',
            'payer_bank' => 'nullable|string|max:80',
            'paid_at' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:255',
            'proof' => 'required|file|max:2048|mimes:jpg,jpeg,png,webp,pdf',
        ], ['proof.required' => 'Lampirkan bukti transfer.', 'proof.mimes' => 'Bukti transfer harus gambar (JPG/PNG/WEBP) atau PDF.', 'proof.max' => 'Ukuran bukti maksimal 2 MB.']);
        $plan = Plan::where('is_active', true)->where('is_public', true)->find($v['plan_id']);
        if (! $plan) {
            return back()->withInput()->with('error', 'Paket tidak tersedia.');
        }
        if (SubscriptionPayment::where('company_id', $company->id)->where('status', 'pending')->exists()) {
            return back()->withInput()->with('error', 'Masih ada pembayaran yang menunggu konfirmasi. Tunggu admin memprosesnya terlebih dulu.');
        }
        $months = $v['billing_cycle'] === 'yearly' ? 12 : (int) ($v['months'] ?? 1);
        $amount = $v['billing_cycle'] === 'yearly' ? $plan->yearlyPrice() : round((float) $plan->price * $months, 2);

        $file = $request->file('proof');
        $ext = strtolower($file->extension() ?: 'bin');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            return back()->withInput()->with('error', 'Format bukti tidak didukung.');
        }
        $dir = storage_path('app/payment-proofs');
        if (! is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'c' . $company->id . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($dir, $name);

        SubscriptionPayment::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'billing_cycle' => $v['billing_cycle'], 'months' => $months, 'amount' => $amount,
            'payer_name' => $v['payer_name'], 'payer_bank' => $v['payer_bank'] ?? null, 'paid_at' => $v['paid_at'], 'notes' => $v['notes'] ?? null,
            'proof_path' => $name, 'status' => 'pending', 'created_by' => auth()->id(),
        ]);

        return redirect()->route('client.billing')->with('success', 'Bukti pembayaran terkirim. Langganan aktif setelah dikonfirmasi admin (biasanya 1×24 jam).');
    }

    public function proof(SubscriptionPayment $payment)
    {
        abort_if($payment->company_id !== auth()->user()->company_id, 404);

        return self::serveProof($payment);
    }

    public static function serveProof(SubscriptionPayment $payment)
    {
        $path = storage_path('app/payment-proofs/' . basename((string) $payment->proof_path));
        abort_unless($payment->proof_path && is_file($path), 404);

        return response()->file($path, ['X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="bukti-' . $payment->id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"']);
    }
}
