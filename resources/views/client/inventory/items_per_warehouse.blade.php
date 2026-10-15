<x-app-layout>
    @section('title', 'Barang per Gudang')
    @section('subtitle', 'Detail kuantitas barang di masing-masing gudang')
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach($warehouses as $w)
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-5"><p class="text-gray-400 text-xs uppercase">{{ $w->name }}</p><p class="text-xl font-bold text-white mt-1">{{ $w->address ?? 'Lokasi belum diatur' }}</p></div>
        @endforeach
    </div>
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Stok Barang ({{ $products->count() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">SKU</th><th class="px-6 py-3">Nama Barang</th><th class="px-6 py-3 text-right">Stok</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($products as $p)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $p->sku }}</td><td class="px-6 py-3 text-white">{{ $p->name }}</td><td class="px-6 py-3 text-right"><span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $p->current_stock > 0 ? 'bg-emerald-900/50 text-emerald-400' : 'bg-red-900/50 text-red-400' }}">{{ $p->current_stock }}</span></td></tr>
                @empty
                <tr><td colspan="3" class="px-6 py-10 text-center text-gray-500">Belum ada barang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
