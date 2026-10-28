<x-app-layout>
    @section('title', 'Konfirmasi Pembayaran')
    @section('subtitle', 'Periksa bukti transfer klien, lalu setujui untuk mengaktifkan/memperpanjang langganan')

    @php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

    <div class="pay-tabs">
        @foreach(['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $k => $l)
            <a href="{{ route('superadmin.payments.index', ['tab' => $k]) }}" class="pay-tab {{ $tab === $k ? 'on' : '' }}">{{ $l }} @if($k !== 'all')<span class="n">{{ $counts[$k] ?? 0 }}</span>@endif</a>
        @endforeach
    </div>

    <div class="pay-card">
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Dikirim</th><th>Perusahaan</th><th>Paket</th><th>Periode</th><th class="r">Jumlah</th><th>Pengirim</th><th>Bukti</th><th>Status</th><th class="c">Tindakan</th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->created_at->format('d M Y H:i') }}</td>
                        <td class="wrap"><b>{{ optional($p->company)->name ?? '(dihapus)' }}</b></td>
                        <td>{{ optional($p->plan)->name }}</td>
                        <td>{{ $p->cycleLabel() }}</td>
                        <td class="r"><b>{{ $rp($p->amount) }}</b></td>
                        <td class="wrap">{{ $p->payer_name }}@if($p->payer_bank) · {{ $p->payer_bank }}@endif<div style="font-size:.7rem;color:var(--text-dim)">transfer {{ optional($p->paid_at)->format('d M Y') }}@if($p->notes) · {{ $p->notes }}@endif</div></td>
                        <td>@if($p->proof_path)<a class="lnk" target="_blank" href="{{ route('superadmin.payments.proof', $p) }}">Lihat</a>@else - @endif</td>
                        <td>
                            @php $st = ['pending' => ['Menunggu', 'st-partial'], 'approved' => ['Disetujui', 'st-paid'], 'rejected' => ['Ditolak', 'st-late']][$p->status] ?? [$p->status, '']; @endphp
                            <span class="st {{ $st[1] }}">{{ $st[0] }}</span>
                            @if($p->reviewed_at)<div style="font-size:.68rem;color:var(--text-dim)">{{ optional($p->reviewer)->name }}, {{ $p->reviewed_at->format('d M') }}</div>@endif
                            @if($p->admin_note)<div style="font-size:.7rem;color:var(--text-dim)" class="wrap">{{ $p->admin_note }}</div>@endif
                        </td>
                        <td class="c" style="white-space:nowrap">
                            @if($p->status === 'pending')
                                <form method="POST" action="{{ route('superadmin.payments.approve', $p) }}" style="display:inline" onsubmit="return confirm('Setujui pembayaran {{ $rp($p->amount) }} dari {{ addslashes(optional($p->company)->name) }}? Langganan langsung aktif/diperpanjang {{ $p->months }} bulan.')">@csrf
                                    <button class="pay-mini prim" type="submit">Setujui</button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.payments.reject', $p) }}" style="display:inline" onsubmit="const n = prompt('Alasan penolakan (dilihat klien):'); if (!n) return false; this.admin_note.value = n; return true;">@csrf
                                    <input type="hidden" name="admin_note"><button class="pay-mini dng" type="submit">Tolak</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="pay-empty">Tidak ada pembayaran di sini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())<div style="padding:10px 18px">{{ $payments->links() }}</div>@endif
    </div>
</x-app-layout>
