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
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:30',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
            'fiscal_year_start' => 'nullable|integer|between:1,12',
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

        // Checkbox mengirim hidden 0 + 1, jadi hanya form yang memuatnya yang mengubah nilai boolean
        foreach (['warn_below_cost', 'prevent_over_receive', 'prevent_prior_transactions', 'notify_approver'] as $flag) {
            if ($request->exists($flag)) {
                $validated[$flag] = $request->boolean($flag);
            } else {
                unset($validated[$flag]);
            }
        }

        // Kolom yang tidak boleh NULL di database: bila form mengirim kosong, pertahankan nilai lama
        foreach (['fiscal_year_start', 'default_tax_rate', 'tax_rounding', 'default_sales_term', 'default_purchase_term',
                  'max_attachment_size', 'allowed_attachment_types', 'price_decimal_format', 'default_language',
                  'inventory_costing_method'] as $notNull) {
            if (array_key_exists($notNull, $validated) && $validated[$notNull] === null) {
                unset($validated[$notNull]);
            }
        }

        unset($validated['logo']);
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $dir = public_path('uploads/logos');
            if (! is_dir($dir)) mkdir($dir, 0755, true);
            $name = 'company-' . $company->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);
            if ($company->logo_path && file_exists(public_path($company->logo_path))) @unlink(public_path($company->logo_path));
            $validated['logo_path'] = 'uploads/logos/' . $name;
        }

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
