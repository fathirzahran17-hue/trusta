@php
    $isService = $kind === 'service';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $self = $isService ? route('client.inventory.services') : route('client.inventory.index');
    $create = $isService ? route('client.inventory.services.create') : route('client.inventory.create');
@endphp
<x-app-layout>
    @section('title', $isService ? 'Jasa' : 'Barang')
    @section('subtitle', $isService ? 'Layanan yang Anda jual — pendapatan jasa, tanpa stok dan tanpa HPP' : 'Barang dagangan — stok, harga jual, dan harga beli')

    <div class="pay-tabs">
        <a href="{{ route('client.inventory.index') }}" class="pay-tab {{ !$isService ? 'on' : '' }}">Barang <span class="n">{{ $counts['goods'] ?? 0 }}</span></a>
        <a href="{{ route('client.inventory.services') }}" class="pay-tab {{ $isService ? 'on' : '' }}">Jasa <span class="n">{{ $counts['service'] ?? 0 }}</span></a>
    </div>

    <form method="GET" action="{{ $self }}" class="rpt-toolbar">
        <div class="rpt-filters">
            <div class="rpt-field"><label>Cari</label><input type="search" name="search" value="{{ request('search') }}" placeholder="Kode / nama"></div>
            <button class="rpt-btn primary" type="submit">Cari</button>
            @if(request('search'))<a href="{{ $self }}" class="rpt-btn">Reset</a>@endif
        </div>
        <div class="rpt-actions"><a href="{{ $create }}" class="rpt-btn primary">+ Tambah {{ $isService ? 'Jasa' : 'Barang' }}</a></div>
    </form>

    <div class="pay-card">
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr>
                    <th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th>
                    <th class="r">{{ $isService ? 'Tarif' : 'Harga Jual' }}</th>
                    @if($isService)<th>Subkontraktor</th>@else<th class="r">Harga Beli</th><th class="r">Stok</th>@endif
                    <th>Status</th><th class="c">Aksi</th>
                </tr></thead>
                <tbody>
                @forelse($products as $product)
                    <tr class="{{ $product->is_active ? '' : 'pay-void-row' }}">
                        <td class="num"><b>{{ $product->sku }}</b></td>
                        <td class="wrap">{{ $product->name }}</td>
                        <td>{{ $product->category->name ?? '-' }}</td>
                        <td>{{ $product->unit ?: '-' }}</td>
                        <td class="r">{{ $rp($product->sell_price) }}</td>
                        @if($isService)
                            <td>@if($product->expense_account_id)<span class="st st-partial">Ya &middot; {{ $rp($product->buy_price) }}</span>@else<span style="color:var(--text-faint)">-</span>@endif</td>
                        @else
                            <td class="r">{{ $rp($product->buy_price) }}</td>
                            <td class="r"><span class="st {{ $product->stock > 0 ? 'st-paid' : 'st-late' }}">{{ $product->stock + 0 }}</span></td>
                        @endif
                        <td>@if($product->is_active)<span class="st st-paid">Aktif</span>@else<span class="st st-void">Nonaktif</span>@endif</td>
                        <td class="c">
                            @unless($isService)<a class="pay-mini" href="{{ route('client.inventory.show', $product) }}">Kartu Stok</a>@endunless
                            <a class="pay-mini prim" href="{{ route('client.inventory.edit', $product) }}">Ubah</a>
                            <form action="{{ route('client.inventory.destroy', $product) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus {{ $isService ? 'jasa' : 'barang' }} ini?')">@csrf @method('DELETE')<button class="pay-mini dng" type="submit">Hapus</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="pay-empty">Belum ada {{ $isService ? 'jasa' : 'barang' }}. <a class="lnk" href="{{ $create }}" style="color:var(--accent);font-weight:600">+ Tambah {{ $isService ? 'Jasa' : 'Barang' }}</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="pay-foot">{{ $products->links() }}</div>@endif
    </div>
</x-app-layout>
