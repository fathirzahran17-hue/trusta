<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Aturan langganan: aktivasi/perpanjangan, trial otomatis, dan kedaluwarsa otomatis. */
class SubscriptionService
{
    /** Hari toleransi setelah tanggal berakhir sebelum akses tulis ditutup. */
    public const GRACE_DAYS = 3;

    /** Langganan yang sedang berlaku (aktif/trial) dengan tanggal berakhir terjauh. */
    public static function current(Company $company): ?Subscription
    {
        return $company->subscriptions()->with('plan')->whereIn('status', ['active', 'trial'])->orderByDesc('end_date')->orderByDesc('id')->first();
    }

    public static function price(Plan $plan, string $cycle): float
    {
        return $cycle === 'yearly' ? $plan->yearlyPrice() : (float) $plan->price;
    }

    /**
     * Aktifkan / perpanjang langganan. Bila masih ada langganan berjalan, periode baru disambung setelahnya.
     */
    public static function activate(Company $company, Plan $plan, int $months, float $amount, string $status = 'active', ?string $note = null, ?int $paymentId = null, ?string $cycle = null): Subscription
    {
        return DB::transaction(function () use ($company, $plan, $months, $amount, $status, $note, $paymentId, $cycle) {
            $cur = self::current($company);
            $start = Carbon::today();
            if ($cur && $cur->end_date && $cur->end_date->gte(Carbon::today()) && $status === 'active' && $cur->status === 'active') {
                $start = $cur->end_date->copy()->addDay();
            }
            if ($cur && $cur->status === 'trial') {
                $cur->update(['status' => 'cancelled', 'notes' => trim(($cur->notes ? $cur->notes . ' · ' : '') . 'Trial diganti paket berbayar')]);
            }
            $sub = $company->subscriptions()->create([
                'plan_id' => $plan->id, 'status' => $status, 'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addMonthsNoOverflow($months)->subDay()->toDateString(),
                'amount_paid' => $amount, 'notes' => $note, 'billing_cycle' => $cycle ?? ($months >= 12 ? 'yearly' : 'monthly'), 'payment_id' => $paymentId,
            ]);
            $company->forceFill(['is_premium' => true])->save();

            return $sub;
        });
    }

    /** Trial otomatis untuk pendaftar baru (diatur di Pengaturan Platform; 0 hari = nonaktif). */
    public static function startTrialIfEnabled(Company $company): ?Subscription
    {
        $days = (int) AppSetting::get('trial_days', 0);
        $plan = Plan::find((int) AppSetting::get('trial_plan_id', 0)) ?? Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->first();
        if ($days <= 0 || ! $plan) {
            return null;
        }
        $sub = $company->subscriptions()->create([
            'plan_id' => $plan->id, 'status' => 'trial', 'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays($days - 1)->toDateString(), 'amount_paid' => 0, 'notes' => "Trial otomatis {$days} hari",
        ]);
        $company->forceFill(['is_premium' => true])->save();

        return $sub;
    }

    /**
     * Langganan yang sudah lewat (lebih dari masa toleransi) ditandai kedaluwarsa dan akses tulis ditutup.
     * Perusahaan premium tanpa catatan langganan (diaktifkan manual) tidak disentuh.
     */
    public static function expireIfOverdue(Company $company): bool
    {
        if (! $company->is_premium || ! $company->subscriptions()->exists()) {
            return false;
        }
        $limit = Carbon::today()->subDays(self::GRACE_DAYS)->toDateString();
        $stillValid = $company->subscriptions()->whereIn('status', ['active', 'trial'])->where('end_date', '>=', $limit)->exists();
        if ($stillValid) {
            return false;
        }
        $company->subscriptions()->whereIn('status', ['active', 'trial'])->update(['status' => 'expired']);
        $company->forceFill(['is_premium' => false])->save();

        return true;
    }

    public static function approve(SubscriptionPayment $p, int $adminId, ?string $note = null): Subscription
    {
        if ($p->status !== 'pending') {
            throw new \DomainException('Pembayaran ini sudah diproses.');
        }

        return DB::transaction(function () use ($p, $adminId, $note) {
            $sub = self::activate($p->company, $p->plan, (int) $p->months, (float) $p->amount, 'active',
                'Pembayaran #' . $p->id . ($note ? ' — ' . $note : ''), $p->id, $p->billing_cycle);
            $p->update(['status' => 'approved', 'admin_note' => $note, 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'subscription_id' => $sub->id]);

            return $sub;
        });
    }
}
