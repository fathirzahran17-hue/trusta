@php
    $sales = $kind === 'sales';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $partyLabel = $sales ? 'Pelanggan' : 'Pemasok';
    $show = $sales ? 'client.sales-invoices.show' : 'client.purchase-invoices.show';
    $settle = $sales ? route('client.sales.settlement') : route('client.purchases.settlement');
    $payUrl = $sales ? route('client.sales.receipts') : route('client.purchases.payments');
@endphp
<x-app-layout>
    @section('title', $sales ? 'Pusat Penjualan' : 'Pusat Pembelian')
    @section('subtitle', $sales ? 'Dari pesanan sampai uang diterima — semua alur penjualan dalam satu tempat' : 'Dari pesanan sampai pembayaran — semua alur pembelian dalam satu tempat')

    <div class="rpt-summary">
        <div class="rpt-stat"><div class="l">{{ $sales ? 'Piutang belum lunas' : 'Hutang belum lunas' }}</div><div class="v">{{ $rp($stats['open']) }}</div></div>
        <div class="rpt-stat {{ $stats['overdue'] > 0 ? 'bad' : '' }}"><div class="l">Lewat jatuh tempo</div><div class="v">{{ $rp($stats['overdue']) }}</div></div>
        <div class="rpt-stat"><div class="l">Faktur belum lunas</div><div class="v">{{ $stats['openCount'] }}</div></div>
        <div class="rpt-stat good"><div class="l">{{ $sales ? 'Penjualan' : 'Pembelian' }} bulan ini</div><div class="v">{{ $rp($stats['month']) }}</div></div>
    </div>

    {{-- ALUR KERJA --}}
    <div class="pay-card">
        <div class="pay-card-head"><h3>Alur {{ $sales ? 'Penjualan' : 'Pembelian' }}</h3><span class="sub">Klik kotak untuk melihat daftar, atau tombol untuk membuat dokumen baru. Jasa tidak melalui {{ $sales ? 'pengiriman' : 'penerimaan barang' }} — langsung ke faktur.</span></div>
        <div class="hub-flow">
            @foreach($flow as $i => [$title, $n, $nLabel, $listRoute, $createRoute, $createLabel])
                <div class="hub-step">
                    <a class="hub-step-main" href="{{ route($listRoute) }}">
                        <span class="hub-no">{{ $i + 1 }}</span>
                        <span class="hub-title">{{ $title }}</span>
                        <span class="hub-count {{ $n > 0 ? 'on' : '' }}"><b>{{ $n }}</b> {{ $nLabel }}</span>
                    </a>
                    <a class="pay-mini prim hub-new" href="{{ route($createRoute) }}">+ {{ $createLabel }}</a>
                </div>
                @if(! $loop->last)<span class="hub-arrow" aria-hidden="true">&rsaquo;</span>@endif
            @endforeach
        </div>
    </div>

    <div class="hub-cols">
        {{-- FAKTUR TERDEKAT --}}
        <div class="pay-card">
            <div class="pay-card-head"><h3>Faktur {{ $sales ? 'yang harus ditagih' : 'yang harus dibayar' }}</h3><a class="pay-mini" href="{{ $settle }}">Lihat semua</a></div>
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>No. Faktur</th><th>{{ $partyLabel }}</th><th>Jatuh Tempo</th><th class="r">Sisa</th><th class="c">Aksi</th></tr></thead>
                    <tbody>
                    @forelse($nearest as $inv)
                        @php $late = $inv->due_date && $inv->due_date->lt(today()); @endphp
                        <tr>
                            <td class="num"><a href="{{ route($show, $inv->id) }}">{{ $inv->invoice_number }}</a></td>
                            <td class="wrap">{{ optional($inv->{$sales ? 'customer' : 'supplier'})->name ?: '-' }}</td>
                            <td>{{ optional($inv->due_date)->format('d M Y') }} @if($late)<span class="st st-late">Lewat</span>@endif</td>
                            <td class="r"><b>{{ $rp($inv->amount_due) }}</b></td>
                            <td class="c"><a class="pay-mini prim" href="{{ $payUrl }}?invoice={{ $inv->id }}">{{ $sales ? 'Terima' : 'Bayar' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pay-empty">Tidak ada faktur yang menunggu {{ $sales ? 'pembayaran' : 'dibayar' }}. 🎉</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SALDO TERBESAR --}}
        <div class="pay-card">
            <div class="pay-card-head"><h3>{{ $partyLabel }} dengan {{ $sales ? 'piutang' : 'hutang' }} terbesar</h3></div>
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>{{ $partyLabel }}</th><th class="r">Faktur</th><th class="r">{{ $sales ? 'Piutang' : 'Hutang' }}</th></tr></thead>
                    <tbody>
                    @forelse($topParties as $p)
                        <tr><td class="wrap">{{ $p['name'] }}</td><td class="r">{{ $p['count'] }}</td><td class="r"><b>{{ $rp($p['due']) }}</b></td></tr>
                    @empty
                        <tr><td colspan="3" class="pay-empty">Belum ada saldo terbuka.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PINTASAN --}}
    <div class="pay-card">
        <div class="pay-card-head"><h3>{{ $sales ? 'Pelanggan & Produk' : 'Pemasok & Barang' }} &middot; Pintasan</h3></div>
        <div class="hub-grid">
            @foreach($shortcuts as [$label, $hint, $route, $createRoute])
                <div class="hub-tile">
                    <a class="hub-tile-main" href="{{ route($route) }}"><b>{{ $label }}</b><span>{{ $hint }}</span></a>
                    @if($createRoute)<a class="hub-tile-add" href="{{ route($createRoute) }}" title="Tambah baru">+</a>@endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
