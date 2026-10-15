<x-app-layout>
    @section('title', 'Barang & Jasa')
    @section('subtitle', 'Daftar master data barang dagangan dan jasa perusahaan')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <form action="{{ route('client.inventory.index') }}" method="GET" class="flex items-center gap-3">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari SKU / nama..."
                    class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm pl-10 w-64">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('client.inventory.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ !request('type') ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Semua</a>
                <a href="{{ route('client.inventory.index', ['type' => 'goods']) }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('type') === 'goods' ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Barang</a>
                <a href="{{ route('client.inventory.index', ['type' => 'service']) }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('type') === 'service' ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Jasa</a>
            </div>
        </form>
        <a href="{{ route('client.inventory.create') }}" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-semibold rounded-lg text-sm transition-colors shadow-lg flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Barang / Jasa
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">SKU</th>
                        <th class="px-6 py-4">Nama</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Satuan</th>
                        <th class="px-6 py-4 text-right">Harga Jual</th>
                        <th class="px-6 py-4 text-right">Harga Beli</th>
                        <th class="px-6 py-4 text-right">Stok</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($products as $product)
                    <tr class="hover:bg-[#0F172A]/50 transition-colors">
                        <td class="px-6 py-4 font-mono text-trusta-teal font-semibold">{{ $product->sku }}</td>
                        <td class="px-6 py-4 text-white font-medium">{{ $product->name }}</td>
                        <td class="px-6 py-4">
                            @if($product->type === 'goods')
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-900/50 text-blue-400">Barang</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-purple-900/50 text-purple-400">Jasa</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-400">{{ $product->category->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-gray-400">{{ $product->unit ?? '-' }}</td>
                        <td class="px-6 py-4 text-right text-white tabular-nums">Rp {{ number_format($product->sell_price, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 text-right text-gray-400 tabular-nums">Rp {{ number_format($product->buy_price, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($product->type === 'goods')
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $product->stock > 0 ? 'bg-emerald-900/50 text-emerald-400' : 'bg-red-900/50 text-red-400' }}">
                                    {{ $product->stock }}
                                </span>
                            @else
                                <span class="text-gray-600">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                @if($product->type === 'goods')
                                <a href="{{ route('client.inventory.show', $product) }}" class="text-blue-400 hover:text-blue-300 transition-colors" title="Kartu Stok">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                @endif
                                <a href="{{ route('client.inventory.edit', $product) }}" class="text-trusta-teal hover:text-white transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('client.inventory.destroy', $product) }}" method="POST" onsubmit="return confirm('Yakin hapus item ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-300 transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-16 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            <p class="text-gray-500 text-sm">Belum ada data barang atau jasa.</p>
                            <a href="{{ route('client.inventory.create') }}" class="inline-block mt-4 px-4 py-2 bg-trusta-teal text-navy-900 font-semibold rounded-lg text-sm hover:bg-teal-400 transition-colors">+ Tambah Barang / Jasa</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-800">{{ $products->links() }}</div>
        @endif
    </div>
</x-app-layout>
