<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Notifikasi di ikon lonceng. Sumbernya:
 *  - Pengumuman dari super admin (semua klien / satu perusahaan)
 *  - Pengingat otomatis: piutang & hutang jatuh tempo, stok menipis, langganan hampir berakhir, status pembayaran langganan
 *  - Untuk super admin: pembayaran langganan menunggu konfirmasi dan pendaftar baru
 * Pengingat harian bertanggal hari ini, jadi muncul lagi esok hari bila masih berlaku.
 */
class NotificationService
{
    /** @return array<int, array{key:string,title:string,body:?string,url:?string,at:Carbon,level:string}> */
    public static function forUser(User $user): array
    {
        return Cache::remember('notif:' . $user->id . ':' . Cache::get('notif_ver', 1), 120, fn () => self::build($user));
    }

    /** Pengumuman berubah: semua cache notifikasi dianggap usang. */
    public static function bump(): void
    {
        Cache::forever('notif_ver', (int) Cache::get('notif_ver', 1) + 1);
    }

    public static function unreadCount(User $user): int
    {
        $read = $user->notifications_read_at ? Carbon::parse($user->notifications_read_at) : null;

        return collect(self::forUser($user))->filter(fn ($n) => ! $read || $n['at']->gt($read))->count();
    }

    private static function build(User $user): array
    {
        $items = [];
        $today = Carbon::today();
        $add = function ($key, $title, $body, $url, $at, $level = 'info') use (&$items) {
            $items[] = ['key' => $key, 'title' => $title, 'body' => $body, 'url' => $url, 'at' => $at instanceof Carbon ? $at : Carbon::parse($at), 'level' => $level];
        };
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        if ($user->isSuperAdmin()) {
            foreach (SubscriptionPayment::with('company')->where('status', 'pending')->orderByDesc('id')->limit(10)->get() as $p) {
                $add('pay' . $p->id, 'Pembayaran langganan menunggu konfirmasi', optional($p->company)->name . ' · ' . $rp($p->amount), route('superadmin.payments.index'), $p->created_at, 'warning');
            }
            foreach (\App\Models\Company::where('created_at', '>=', now()->subDays(7))->orderByDesc('created_at')->limit(10)->get() as $c) {
                $add('reg' . $c->id, 'Pendaftar baru', $c->name . ' (' . \App\Support\BusinessType::label($c->business_type) . ')', route('superadmin.clients.index'), $c->created_at);
            }
        } elseif ($user->company) {
            $cid = (int) $user->company_id;
            foreach (Announcement::visibleFor($cid)->orderByDesc('id')->limit(10)->get() as $a) {
                $add('ann' . $a->id, $a->title, $a->body, $a->url, $a->created_at, $a->level);
            }
            $ar = SalesInvoice::where('company_id', $cid)->whereIn('status', ['unpaid', 'partial'])->where('amount_due', '>', 0);
            $overAr = (clone $ar)->where('due_date', '<', $today->toDateString());
            if ($n = (clone $overAr)->count()) {
                $add('ar-over', "{$n} piutang lewat jatuh tempo", 'Sisa tagihan ' . $rp((clone $overAr)->sum('amount_due')) . '. Segera tagih pelanggan.', route('client.sales.settlement', ['tab' => 'overdue']), $today, 'warning');
            }
            $soonAr = (clone $ar)->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(3)->toDateString()]);
            if ($n = (clone $soonAr)->count()) {
                $add('ar-soon', "{$n} piutang jatuh tempo ≤ 3 hari", $rp((clone $soonAr)->sum('amount_due')), route('client.sales.settlement'), $today);
            }
            $ap = PurchaseInvoice::where('company_id', $cid)->whereIn('status', ['unpaid', 'partial'])->where('amount_due', '>', 0);
            $overAp = (clone $ap)->where('due_date', '<', $today->toDateString());
            if ($n = (clone $overAp)->count()) {
                $add('ap-over', "{$n} hutang lewat jatuh tempo", 'Perlu dibayar ' . $rp((clone $overAp)->sum('amount_due')) . '.', route('client.purchases.settlement', ['tab' => 'overdue']), $today, 'warning');
            }
            $soonAp = (clone $ap)->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(3)->toDateString()]);
            if ($n = (clone $soonAp)->count()) {
                $add('ap-soon', "{$n} hutang jatuh tempo ≤ 3 hari", $rp((clone $soonAp)->sum('amount_due')), route('client.purchases.payments'), $today);
            }
            if (\App\Support\BusinessType::hasStock($user->company->business_type)) {
                $low = Product::where('company_id', $cid)->where('type', 'goods')->where('is_active', true)->get()->filter(fn ($p) => (float) $p->stock <= 5)->count();
                if ($low) {
                    $add('stock', "{$low} barang stok menipis", 'Stok 5 atau kurang.', route('client.inventory.minimum_stock'), $today, 'warning');
                }
            }
            if ($user->role === 'client_admin') {
                if ($cur = SubscriptionService::current($user->company)) {
                    $left = (int) $today->diffInDays($cur->end_date, false);
                    if ($left <= 7) {
                        $add('sub', $left >= 0 ? 'Langganan berakhir dalam ' . $left . ' hari' : 'Langganan sudah berakhir', 'Berlaku sampai ' . $cur->end_date->format('d M Y') . '.', route('client.billing'), $today, 'warning');
                    }
                }
                foreach (SubscriptionPayment::where('company_id', $cid)->whereIn('status', ['approved', 'rejected'])->where('reviewed_at', '>=', now()->subDays(14))->get() as $p) {
                    $ok = $p->status === 'approved';
                    $add('subpay' . $p->id, $ok ? 'Pembayaran langganan disetujui' : 'Pembayaran langganan ditolak', $ok ? 'Langganan Anda sudah aktif/diperpanjang.' : $p->admin_note, route('client.billing'), $p->reviewed_at, $ok ? 'success' : 'warning');
                }
            }
        }

        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);

        return $items;
    }
}
