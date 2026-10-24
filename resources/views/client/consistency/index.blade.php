<x-app-layout>
    @section('title', 'Cek Konsistensi Data')
    @section('subtitle', 'Mencari faktur, pembayaran, atau jurnal yang tidak sinkron dengan Buku Besar')

    @php $total = collect($issues)->flatten(1)->count(); @endphp

    @if($total === 0)
        <div class="pay-card" style="padding: 28px; text-align:center;">
            <div style="font-size:1.05rem; font-weight:700; color: var(--text-strong);">Semua konsisten</div>
            <p style="color: var(--text-muted); font-size:.82rem; margin-top:6px;">Jurnal seimbang, setiap faktur & pembayaran aktif punya jurnal, dan yang dibatalkan sudah dibalik.</p>
        </div>
    @else
        <div class="rpt-summary">
            <div class="rpt-stat bad"><div class="l">Temuan</div><div class="v">{{ $total }}</div></div>
        </div>
        @foreach($issues as $group => $rows)
            <div class="pay-card" style="margin-bottom: 14px;">
                <div style="padding: 10px 14px; font-weight: 700; font-size: .85rem; color: var(--text-strong); border-bottom: 1px solid var(--border);">{{ $group }} <span style="color: var(--text-dim); font-weight: 500;">({{ count($rows) }})</span></div>
                <table class="pay-table">
                    <tbody>
                    @foreach($rows as $r)
                        <tr>
                            <td class="wrap">{{ $r['text'] }}@if($r['hint'])<div style="font-size:.7rem; color: var(--text-dim); margin-top:2px;">{{ $r['hint'] }}</div>@endif</td>
                            <td class="r" style="white-space:nowrap;">
                                @if($r['fix'])
                                    <form method="POST" action="{{ $r['fix']['url'] }}" style="display:inline" onsubmit="return confirm('Buat ulang jurnal faktur ini sekarang?')">@csrf
                                        <button type="submit" class="btn-primary" style="font-size:.72rem; padding:4px 10px;">{{ $r['fix']['label'] }}</button>
                                    </form>
                                @endif
                                @if($r['url'])<a class="lnk" href="{{ $r['url'] }}" style="margin-left:10px;">Buka</a>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</x-app-layout>
