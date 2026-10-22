@php
    $sales = $kind === 'sales';
    $rp = fn ($n) => 'Rp ' . number_format($n, 2, ',', '.');
    $partyLabel = $sales ? 'Pelanggan' : 'Pemasok';
    $self = $sales ? route('client.sales.settlement') : route('client.purchases.settlement');
    $payUrl = $sales ? route('client.sales.receipts') : route('client.purchases.payments');
    $show = $sales ? 'client.sales-invoices.show' : 'client.purchase-invoices.show';
    $qs = fn ($over = []) => http_build_query(array_filter(array_merge(['tab' => $tab, 'q' => $q, 'party' => $partyId], $over), fn ($v) => $v !== '' && $v !== null && $v !== 0));
@endphp
<x-app-layout>
    @section('title', 'Status Pelunasan ' . ($sales ? 'Penjualan' : 'Pembelian'))
    @section('subtitle', $sales ? 'Pilah faktur penjualan yang sudah lunas dan yang masih menunggu pembayaran' : 'Pilah faktur pembelian yang sudah lunas dan yang masih harus dibayar')

    <div class="rpt-summary">
        <div class="rpt-stat"><div class="l">{{ $sales ? 'Piutang belum lunas' : 'Hutang belum lunas' }}</div><div class="v">{{ $rp($stats['open']) }}</div></div>
        <div class="rpt-stat {{ $stats['overdue'] > 0 ? 'bad' : '' }}"><div class="l">Lewat jatuh tempo</div><div class="v">{{ $rp($stats['overdue']) }}</div></div>
        <div class="rpt-stat good"><div class="l">Total faktur lunas</div><div class="v">{{ $rp($stats['paid']) }}</div></div>
    </div>

    <div class="pay-tabs">
        @foreach($tabs as $key => [$label, $n])
            <a href="{{ $self }}?{{ $qs(['tab' => $key, 'page' => null]) }}" class="pay-tab {{ $tab === $key ? 'on' : '' }}">{{ $label }} <span class="n">{{ $n }}</span></a>
        @endforeach
    </div>

    <form method="GET" action="{{ $self }}" class="rpt-toolbar">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="rpt-filters">
            <div class="rpt-field"><label>Cari</label><input type="search" name="q" value="{{ $q }}" placeholder="No. faktur / nama"></div>
            <div class="rpt-field"><label>{{ $partyLabel }}</label>
                <select name="party"><option value="">Semua</option>
                    @foreach($parties as $p)<option value="{{ $p->id }}" @selected($partyId === $p->id)>{{ $p->name }}</option>@endforeach
                </select></div>
            <button class="rpt-btn primary" type="submit">Terapkan</button>
            @if($q !== '' || $partyId)<a href="{{ $self }}?tab={{ $tab }}" class="rpt-btn">Reset</a>@endif
        </div>
        <div class="rpt-actions">
            <a href="{{ $payUrl }}" class="rpt-btn primary">{{ $sales ? 'Terima Pembayaran' : 'Catat Pembayaran' }}</a>
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr>
                    <th>No. Faktur</th><th>{{ $partyLabel }}</th><th>Tgl Faktur</th><th>Jatuh Tempo</th>
                    <th class="r">Total</th><th class="r">Dibayar</th><th class="r">Sisa</th><th>Status</th><th class="c">Aksi</th>
                </tr></thead>
                <tbody>
                    @forelse($invoices as $inv)
                        @php $pct = $inv->total > 0 ? min(100, round(($inv->total > 0 ? $inv->amount_paid / $inv->total : 0) * 100)) : 0; $name = optional($inv->{$sales ? 'customer' : 'supplier'})->name; @endphp
                        <tr class="{{ $inv->status === 'void' ? 'pay-void-row' : '' }}">
                            <td class="num"><a href="{{ route($show, $inv->id) }}">{{ $inv->invoice_number }}</a></td>
                            <td class="wrap">{{ $name ?: '-' }}</td>
                            <td>{{ optional($inv->invoice_date)->format('d M Y') }}</td>
                            <td>{{ optional($inv->due_date)->format('d M Y') }}</td>
                            <td class="r">{{ $rp($inv->total) }}</td>
                            <td class="r">{{ $rp($inv->amount_paid) }}<div class="pay-bar"><i style="width:{{ $pct }}%"></i></div></td>
                            <td class="r"><b>{{ $rp($inv->amount_due) }}</b></td>
                            <td>@include('partials.invoice-status', ['invoice' => $inv])</td>
                            <td class="c">
                                @if(in_array($inv->status, ['unpaid', 'partial']))
                                    <a class="pay-mini prim" href="{{ $payUrl }}?invoice={{ $inv->id }}">{{ $sales ? 'Terima' : 'Bayar' }}</a>
                                @endif
                                <a class="pay-mini" href="{{ route($show, $inv->id) }}">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="pay-empty">Tidak ada faktur pada kategori ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())<div class="pay-foot">{{ $invoices->links() }}</div>@endif
    </div>
</x-app-layout>
