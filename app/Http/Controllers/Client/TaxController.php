<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Account;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index()
    {
        $taxes = Tax::with(['salesTaxAccount', 'purchaseTaxAccount'])
            ->where('company_id', auth()->user()->company_id)
            ->get();
        return view('client.taxes.index', compact('taxes'));
    }

    public function create()
    {
        $liabilityAccounts = Account::where('company_id', auth()->user()->company_id)
            ->whereIn('type', ['current_liability', 'liability'])
            ->orderBy('code')
            ->get();
            
        $assetAccounts = Account::where('company_id', auth()->user()->company_id)
            ->whereIn('type', ['current_asset', 'asset'])
            ->orderBy('code')
            ->get();
            
        return view('client.taxes.create', compact('liabilityAccounts', 'assetAccounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'sales_tax_account_id' => 'nullable|exists:accounts,id',
            'purchase_tax_account_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        Tax::create(array_merge($request->all(), [
            'company_id' => auth()->user()->company_id,
            'is_active' => $request->has('is_active'),
        ]));

        return redirect()->route('client.taxes.index')->with('success', 'Tarif pajak berhasil ditambahkan.');
    }

    public function edit(Tax $tax)
    {
        if ($tax->company_id !== auth()->user()->company_id) abort(403);
        
        $liabilityAccounts = Account::where('company_id', auth()->user()->company_id)
            ->whereIn('type', ['current_liability', 'liability'])
            ->orderBy('code')
            ->get();
            
        $assetAccounts = Account::where('company_id', auth()->user()->company_id)
            ->whereIn('type', ['current_asset', 'asset'])
            ->orderBy('code')
            ->get();
            
        return view('client.taxes.edit', compact('tax', 'liabilityAccounts', 'assetAccounts'));
    }

    public function update(Request $request, Tax $tax)
    {
        if ($tax->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'sales_tax_account_id' => 'nullable|exists:accounts,id',
            'purchase_tax_account_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        $data['is_active'] = $request->has('is_active');
        $tax->update($data);

        return redirect()->route('client.taxes.index')->with('success', 'Tarif pajak berhasil diperbarui.');
    }

    public function destroy(Tax $tax)
    {
        if ($tax->company_id !== auth()->user()->company_id) abort(403);
        
        $tax->delete();
        return redirect()->route('client.taxes.index')->with('success', 'Tarif pajak berhasil dihapus.');
    }
}
