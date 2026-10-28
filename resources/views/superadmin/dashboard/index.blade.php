<x-app-layout>
    @section('title', 'Dashboard Platform')
    @section('subtitle', 'Ringkasan klien, langganan, dan pendapatan TRUSTA')

    @php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

    <div class="rpt-summary">
        <div class="rpt-stat"><div class="l">Total Klien</div><div class="v">{{ number_format($stats['companies']) }}</div><div style="font-size:.7rem;color:var(--text-dim)">+{{ $stats['new_month'] }} bulan ini · {{ $stats['users'] }} pengguna</div></div>
        <div class="rpt-stat good"><div class="l">Langganan Berbayar</div><div class="v">{{ $stats['paid'] }}</div><div style="font-size:.7rem;color:var(--text-dim)">{{ $stats['trial'] }} trial · {{ $stats['inactive'] }} tidak aktif</div></div>
        <div class="rpt-stat"><div class="l">Pendapatan Bulanan (MRR)</div><div class="v">{{ $rp($stats['mrr']) }}</div><div style="font-size:.7rem;color:var(--text-dim)">dari langganan berbayar berjalan</div></div>
        <div class="rpt-stat"><div class="l">Diterima Bulan Ini</div><div class="v">{{ $rp($stats['revenue_month']) }}</div><div style="font-size:.7rem;color:var(--text-dim)">pembayaran disetujui + aktivasi manual</div></div>
        <a href="{{ route('superadmin.payments.index') }}" class="rpt-stat {{ $stats['pending'] ? 'bad' : '' }}" style="text-decoration:none"><div class="l">Pembayaran Menunggu</div><div class="v">{{ $stats['pending'] }}</div><div style="font-size:.7rem;color:var(--text-dim)">klik untuk konfirmasi</div></a>
    </div>

    <div class="sa-grid">
        <div class="pay-card">
            <div class="pay-card-head"><h3>Pembayaran Menunggu Konfirmasi</h3><a class="pay-mini" href="{{ route('superadmin.payments.index') }}">Semua</a></div>
            <table class="pay-table"><tbody>
                @forelse($pendingPayments as $p)
                    <tr><td class="wrap"><b>{{ optional($p->company)->name }}</b><div style="font-size:.7rem;color:var(--text-dim)">{{ optional($p->plan)->name }} · {{ $p->cycleLabel() }} · {{ $p->created_at->diffForHumans() }}</div></td><td class="r"><b>{{ $rp($p->amount) }}</b></td></tr>
                @empty
                    <tr><td class="pay-empty">Tidak ada yang menunggu.</td></tr>
                @endforelse
            </tbody></table>
        </div>

        <div class="pay-card">
            <div class="pay-card-head"><h3>Berakhir dalam 14 Hari</h3><a class="pay-mini" href="{{ route('superadmin.clients.index', ['tab' => 'expiring']) }}">Semua</a></div>
            <table class="pay-table"><tbody>
                @forelse($expiring as $s)
                    @php $left = (int) now()->startOfDay()->diffInDays($s->end_date, false); @endphp
                    <tr><td class="wrap"><b>{{ optional($s->company)->name }}</b><div style="font-size:.7rem;color:var(--text-dim)">{{ optional($s->plan)->name }} · {{ $s->status === 'trial' ? 'Trial' : 'Berbayar' }}</div></td>
                        <td class="r">{{ $s->end_date->format('d M Y') }}<div style="font-size:.7rem;color:var(--danger)">{{ $left === 0 ? 'hari ini' : 'sisa ' . $left . ' hari' }}</div></td></tr>
                @empty
                    <tr><td class="pay-empty">Tidak ada langganan yang segera berakhir.</td></tr>
                @endforelse
            </tbody></table>
        </div>

        <div class="pay-card">
            <div class="pay-card-head"><h3>Pendaftar Terbaru</h3><a class="pay-mini" href="{{ route('superadmin.clients.index') }}">Semua klien</a></div>
            <table class="pay-table"><tbody>
                @forelse($recent as $c)
                    <tr><td class="wrap"><b>{{ $c->name }}</b><div style="font-size:.7rem;color:var(--text-dim)">{{ \App\Support\BusinessType::label($c->business_type) }} · {{ $c->users_count }} pengguna</div></td>
                        <td class="r">{{ $c->created_at->format('d M Y') }}<div>@if($c->is_premium)<span class="st st-paid">Aktif</span>@else<span class="st st-void" style="text-decoration:none">Tidak aktif</span>@endif</div></td></tr>
                @empty
                    <tr><td class="pay-empty">Belum ada klien.</td></tr>
                @endforelse
            </tbody></table>
        </div>

        <div class="pay-card">
            <div class="pay-card-head"><h3>Komposisi Klien</h3></div>
            <div style="padding: 14px 18px; display:grid; gap:14px">
                <div>
                    <div style="font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color: var(--text-dim); margin-bottom:8px">Bidang Usaha</div>
                    @php $tot = max(1, $byType->sum()); @endphp
                    @foreach(\App\Support\BusinessType::TYPES as $k => $t)
                        @php $n = (int) ($byType[$k] ?? 0); @endphp
                        <div style="display:flex; justify-content:space-between; font-size:.8rem; color: var(--text)"><span>{{ $t['label'] }}</span><b>{{ $n }}</b></div>
                        <div class="sa-bar" style="margin:3px 0 8px"><i style="width: {{ round($n / $tot * 100) }}%"></i></div>
                    @endforeach
                </div>
                <div>
                    <div style="font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color: var(--text-dim); margin-bottom:8px">Paket Berjalan</div>
                    @forelse($byPlan as $name => $n)
                        <div style="display:flex; justify-content:space-between; font-size:.8rem; color: var(--text); padding:2px 0"><span>{{ $name }}</span><b>{{ $n }}</b></div>
                    @empty
                        <div style="font-size:.8rem; color: var(--text-dim)">Belum ada langganan berjalan.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
