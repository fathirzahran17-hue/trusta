@php
    $isAll = $kind === 'all';
    $isService = $kind === 'service';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $self = $isAll ? route('client.inventory.all') : ($isService ? route('client.inventory.services') : route('client.inventory.index'));
    $create = $isService ? route('client.inventory.services.create') : route('client.inventory.create');
@endphp
<x-app-layout>
    @section('title', $isAll ? 'Produk & Jasa' : ($isService ? 'Jasa' : 'Barang'))
    @section('subtitle', $isAll ? 'Semua yang Anda jual dan beli — barang (bersetok) dan jasa dalam satu daftar' : ($isService ? 'Layanan yang Anda jual — pendapatan jasa, tanpa stok dan tanpa HPP' : 'Barang dagangan — stok, harga jual, dan harga beli'))

    <div class="pay-tabs">
        <a href="{{ route('client.inventory.all') }}" class="pay-tab {{ $isAll ? 'on' : '' }}">Semua <span class="n">{{ ($counts['goods'] ?? 0) + ($counts['service'] ?? 0) }}</span></a>
        <a href="{{ route('client.inventory.index') }}" class="pay-tab {{ (!$isService && !$isAll) ? 'on' : '' }}">Barang <span class="n">{{ $counts['goods'] ?? 0 }}</span></a>
        <a href="{{ route('client.inventory.services') }}" class="pay-tab {{ $isService ? 'on' : '' }}">Jasa <span class="n">{{ $counts['service'] ?? 0 }}</span></a>
    </div>

    <form method="GET" action="{{ $self }}" class="rpt-toolbar">
        <div class="rpt-filters">
            <div class="rpt-field"><label>Cari</label><input type="search" name="search" value="{{ request('search') }}" placeholder="Kode / nama"></div>
            <button class="rpt-btn primary" type="submit">Cari</button>
            @if(request('search'))<a href="{{ $self }}" class="rpt-btn">Reset</a>@endif
        </div>
        <div class="rpt-actions">
            @if($isAll)<a href="{{ route('client.inventory.create') }}" class="rpt-btn">+ Barang</a><a href="{{ route('client.inventory.services.create') }}" class="rpt-btn primary">+ Jasa</a>
            @else<a href="{{ $create }}" class="rpt-btn primary">+ Tambah {{ $isService ? 'Jasa' : 'Barang' }}</a>@endif
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr>
                    <th>Kode</th><th>Nama</th>@if($isAll)<th>Jenis</th>@endif<th>Kategori</th><th>Satuan</th>
                    <th class="r">{{ $isService ? 'Tarif' : 'Harga Jual' }}</th>
                    @if($isService)<th>Subkontraktor</th>@else<th class="r">Harga Beli</th><th class="r">Stok</th>@endif
                    <th>Status</th><th class="c">Aksi</th>
                </tr></thead>
                <tbody>
                @forelse($products as $product)
                    <tr class="{{ $product->is_active ? '' : 'pay-void-row' }}">
                        <td class="num"><b>{{ $product->sku }}</b></td>
                        <td class="wrap">{{ $product->name }}</td>
                        @if($isAll)<td><span class="st {{ $product->type === 'service' ? 'st-unpaid' : 'st-paid' }}">{{ $product->type === 'service' ? 'Jasa' : 'Barang' }}</span></td>@endif
                        <td>{{ $product->category->name ?? '-' }}</td>
                        <td>{{ $product->unit ?: '-' }}</td>
                        <td class="r">{{ $rp($product->sell_price) }}</td>
                        @if($isService)
                            <td>@if($product->expense_account_id)<span class="st st-partial">Ya &middot; {{ $rp($product->buy_price) }}</span>@else<span style="color:var(--text-faint)">-</span>@endif</td>
                        @elseif($isAll && $product->type === 'service')
                            <td class="r">{{ $product->expense_account_id ? $rp($product->buy_price) : '-' }}</td>
                            <td class="r"><span style="color:var(--text-faint)">-</span></td>
                        @else
                            <td class="r">{{ $rp($product->buy_price) }}</td>
                            <td class="r"><span class="st {{ $product->stock > 0 ? 'st-paid' : 'st-late' }}">{{ $product->stock + 0 }}</span></td>
                        @endif
                        <td>@if($product->is_active)<span class="st st-paid">Aktif</span>@else<span class="st st-void">Nonaktif</span>@endif</td>
                        <td class="c">
                            @if($product->type === 'goods')<a class="pay-mini" href="{{ route('client.inventory.show', $product) }}">Kartu Stok</a>@endif
                            <a class="pay-mini prim" href="{{ route('client.inventory.edit', $product) }}">Ubah</a>
                            <form action="{{ route('client.inventory.destroy', $product) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus {{ $product->type === 'service' ? 'jasa' : 'barang' }} ini?')">@csrf @method('DELETE')<button class="pay-mini dng" type="submit">Hapus</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="pay-empty">Belum ada {{ $isAll ? 'produk atau jasa' : ($isService ? 'jasa' : 'barang') }}. @if($isAll)<a class="lnk" href="{{ route('client.inventory.create') }}" style="color:var(--accent);font-weight:600">+ Barang</a> &nbsp;·&nbsp; <a class="lnk" href="{{ route('client.inventory.services.create') }}" style="color:var(--accent);font-weight:600">+ Jasa</a>@else<a class="lnk" href="{{ $create }}" style="color:var(--accent);font-weight:600">+ Tambah {{ $isService ? 'Jasa' : 'Barang' }}</a>@endif</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="pay-foot">{{ $products->links() }}</div>@endif
    </div>
</x-app-layout>
