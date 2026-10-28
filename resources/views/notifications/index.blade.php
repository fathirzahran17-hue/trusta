<x-app-layout>
    @section('title', 'Notifikasi')
    @section('subtitle', 'Pengumuman admin dan pengingat otomatis (jatuh tempo, stok, langganan)')

    @php $read = $readAt ? \Illuminate\Support\Carbon::parse($readAt) : null; @endphp

    <div class="pay-card" style="max-width: 760px">
        <div class="pay-card-head">
            <h3>Semua Notifikasi ({{ count($items) }})</h3>
            <form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="pay-mini" type="submit">Tandai semua dibaca</button></form>
        </div>
        @forelse($items as $n)
            <a href="{{ $n['url'] ?: '#' }}" class="notif-item lvl-{{ $n['level'] }} {{ (! $read || $n['at']->gt($read)) ? 'unread' : '' }}">
                <span class="notif-dot"></span>
                <span><b>{{ $n['title'] }}</b>@if($n['body'])<small>{{ $n['body'] }}</small>@endif<em>{{ $n['at']->format('d M Y') }}{{ $n['at']->format('H:i') !== '00:00' ? ' ' . $n['at']->format('H:i') : '' }}</em></span>
            </a>
        @empty
            <div class="notif-empty">Belum ada notifikasi.</div>
        @endforelse
    </div>
    <p class="pay-flow" style="max-width:760px">Pengingat jatuh tempo, stok, dan langganan dihitung otomatis setiap hari dan hilang sendiri setelah diselesaikan. Pengumuman dikirim oleh admin TRUSTA.</p>
</x-app-layout>
