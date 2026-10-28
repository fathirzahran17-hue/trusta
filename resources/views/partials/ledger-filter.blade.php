@php
    $presets = ['today' => 'Hari ini', 'month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'year' => 'Tahun ini', 'all' => 'Semua'];
    $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
    $label = $period['from'] || $period['to']
        ? ($fmt($period['from']) ?: 'awal') . ' – ' . ($fmt($period['to']) ?: 'sekarang')
        : 'Semua tanggal';
@endphp
<form method="GET" action="{{ url()->current() }}" class="lf-bar no-print">
    <div class="lf-presets">
        @foreach($presets as $key => $text)
            <a href="{{ url()->current() }}?range={{ $key }}" class="lf-chip {{ $period['preset'] === $key ? 'on' : '' }}">{{ $text }}</a>
        @endforeach
    </div>
    <div class="lf-range">
        <label>Dari <input type="date" name="from" value="{{ $period['from'] }}"></label>
        <label>Sampai <input type="date" name="to" value="{{ $period['to'] }}"></label>
        <button type="submit" class="lf-go">Tampilkan</button>
    </div>
    <div class="lf-info">Periode: <b>{{ $label }}</b> · {{ count($mutations) }} dari {{ $totalCount }} mutasi
        @if(!empty($period['show_void']))
            · <a href="{{ url()->current() }}?void=0" class="lnk" style="color:var(--accent)">Sembunyikan transaksi dibatalkan</a>
        @elseif(!empty($period['hidden_void']))
            · {{ $period['hidden_void'] }} transaksi dibatalkan disembunyikan (<a href="{{ url()->current() }}?void=1" class="lnk" style="color:var(--accent)">tampilkan</a>)
        @endif
    </div>
</form>
