{{-- Pengingat langganan untuk admin klien: hampir berakhir (≤7 hari) atau tidak aktif --}}
@php
    $u = auth()->user();
    $subBanner = null;
    if ($u && $u->role === 'client_admin' && $u->company && !request()->routeIs('client.billing*')) {
        $cur = \App\Services\SubscriptionService::current($u->company);
        if (!$u->company->is_premium) {
            $subBanner = ['bad', 'Langganan tidak aktif — data hanya bisa dilihat. Aktifkan paket untuk kembali menambah & mengubah transaksi.'];
        } elseif ($cur) {
            $left = (int) now()->startOfDay()->diffInDays($cur->end_date, false);
            if ($left < 0) $subBanner = ['bad', 'Langganan sudah berakhir ' . abs($left) . ' hari lalu (masa toleransi ' . \App\Services\SubscriptionService::GRACE_DAYS . ' hari). Segera perpanjang.'];
            elseif ($left <= 7) $subBanner = ['', ($cur->status === 'trial' ? 'Masa trial' : 'Langganan') . ' berakhir ' . ($left === 0 ? 'hari ini' : 'dalam ' . $left . ' hari') . ' (' . $cur->end_date->format('d M Y') . ').'];
        }
    }
@endphp
@if($subBanner)
    <div class="sub-banner {{ $subBanner[0] }}"><span>{{ $subBanner[1] }}</span><a href="{{ route('client.billing') }}">Kelola Langganan →</a></div>
@endif
