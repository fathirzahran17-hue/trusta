<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PaymentTerm;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    private function companyId() { return auth()->user()->company_id; }

    public function paymentTerms()
    {
        $terms = PaymentTerm::where('company_id', $this->companyId())->orderBy('days')->get();
        return view('client.company.payment_terms', compact('terms'));
    }

    public function storePaymentTerm(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'days' => 'required|integer|min:0', 'description' => 'nullable|string|max:255']);
        PaymentTerm::create(['company_id' => $this->companyId(), 'name' => $request->name, 'days' => $request->days, 'description' => $request->description]);
        return redirect()->route('client.settings.payment_terms')->with('success', 'Syarat pembayaran berhasil ditambahkan.');
    }

    public function destroyPaymentTerm(PaymentTerm $paymentTerm)
    {
        if ($paymentTerm->company_id != $this->companyId()) abort(404);
        $paymentTerm->delete();
        return redirect()->route('client.settings.payment_terms')->with('success', 'Syarat pembayaran berhasil dihapus.');
    }

    public function monthEnd()
    {
        $company = auth()->user()->company;
        return view('client.company.month_end', compact('company'));
    }

    public function processMonthEnd(Request $request)
    {
        $request->validate([
            'closing_date' => 'required|date',
        ]);

        $company = auth()->user()->company;
        $company->closing_date = $request->closing_date;
        $company->save();

        \App\Models\ActivityLog::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'action' => 'close_period',
            'model_type' => get_class($company),
            'model_id' => $company->id,
            'description' => 'Tutup Buku / Kunci Transaksi hingga ' . $request->closing_date,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Tutup Buku berhasil dilakukan. Transaksi sebelum atau pada tanggal ' . date('d/m/Y', strtotime($request->closing_date)) . ' kini terkunci.');
    }
}
