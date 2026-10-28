<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Plan;
use Illuminate\Http\Request;

/** Pengaturan platform: rekening tujuan transfer, kontak admin, trial otomatis pendaftar baru. */
class SettingController extends Controller
{
    public const KEYS = ['bank_accounts', 'admin_whatsapp', 'admin_email', 'trial_days', 'trial_plan_id', 'billing_note'];

    public function index()
    {
        $settings = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => AppSetting::get($k)])->all();
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();

        return view('superadmin.settings.index', compact('settings', 'plans'));
    }

    public function update(Request $request)
    {
        $v = $request->validate([
            'bank_accounts' => 'nullable|string|max:1000',
            'admin_whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ]*$/'],
            'admin_email' => 'nullable|email|max:150',
            'trial_days' => 'nullable|integer|min:0|max:90',
            'trial_plan_id' => 'nullable|integer|exists:plans,id',
            'billing_note' => 'nullable|string|max:500',
        ]);
        foreach (self::KEYS as $k) {
            AppSetting::put($k, $v[$k] ?? null);
        }

        return back()->with('success', 'Pengaturan platform disimpan.');
    }
}
