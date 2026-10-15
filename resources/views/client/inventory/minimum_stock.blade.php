<x-app-layout>
    @section('title', 'Barang Stok Minimum')
    @section('subtitle', 'Barang yang stoknya rendah dan perlu segera diisi ulang')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Barang Stok Rendah ({{ $lowStock->count() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">SKU</th><th class="px-6 py-3">Nama Barang</th><th class="px-6 py-3 text-right">Stok Saat Ini</th><th class="px-6 py-3 text-center">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($lowStock as $p)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $p->sku }}</td><td class="px-6 py-3 text-white font-medium">{{ $p->name }}</td><td class="px-6 py-3 text-right"><span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $p->current_stock <= 0 ? 'bg-red-900/50 text-red-400' : 'bg-amber-900/50 text-amber-400' }}">{{ $p->current_stock }}</span></td><td class="px-6 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-900/50 text-red-400">{{ $p->current_stock <= 0 ? 'Habis' : 'Hampir Habis' }}</span></td></tr>
                @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500 text-lg">Semua stok barang aman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
