<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function index()
    {
        $company = auth()->user()->company;
        return view('client.preferences.index', compact('company'));
    }

    public function update(Request $request)
    {
        $company = auth()->user()->company;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'fiscal_year_start' => 'nullable|date',
            'address' => 'nullable|string',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rounding' => 'nullable|string',
            'default_sales_term' => 'nullable|string',
            'warn_below_cost' => 'nullable|boolean',
            'default_purchase_term' => 'nullable|string',
            'prevent_over_receive' => 'nullable|boolean',
            'prevent_prior_transactions' => 'nullable|boolean',
            'closing_date' => 'nullable|date',
            'notify_approver' => 'nullable|boolean',
            'max_attachment_size' => 'nullable|integer|min:1',
            'allowed_attachment_types' => 'nullable|string',
            'custom_customer_attr_1' => 'nullable|string',
            'custom_item_attr_1' => 'nullable|string',
            'price_decimal_format' => 'nullable|string',
            'default_language' => 'nullable|string',
            'inventory_costing_method' => 'nullable|in:average,fifo',
        ]);

        // Default booleans to false if not present in request (since checkboxes don't send anything when unchecked)
        $validated['warn_below_cost'] = $request->has('warn_below_cost');
        $validated['prevent_over_receive'] = $request->has('prevent_over_receive');
        $validated['prevent_prior_transactions'] = $request->has('prevent_prior_transactions');
        $validated['notify_approver'] = $request->has('notify_approver');

        $oldData = $company->only(array_keys($validated));
        $company->update($validated);
        $newData = $company->only(array_keys($validated));

        \App\Models\ActivityLog::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'action' => 'update',
            'model_type' => get_class($company),
            'model_id' => $company->id,
            'description' => 'Mengubah Preferensi Perusahaan',
            'changes' => [
                'before' => $oldData,
                'after' => $newData,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Preferensi perusahaan berhasil diperbarui.');
    }
}
