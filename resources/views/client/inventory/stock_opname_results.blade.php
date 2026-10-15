<x-app-layout>
    @section('title', 'Hasil Stok Opname')
    @section('subtitle', 'Input dan lihat hasil perhitungan fisik persediaan')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Perbandingan Stok</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">SKU</th><th class="px-6 py-3">Nama Barang</th><th class="px-6 py-3 text-right">Stok Sistem</th><th class="px-6 py-3 text-right">Stok Fisik</th><th class="px-6 py-3 text-right">Selisih</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($products as $p)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $p->sku }}</td><td class="px-6 py-3 text-white">{{ $p->name }}</td><td class="px-6 py-3 text-right tabular-nums text-white">{{ $p->current_stock }}</td><td class="px-6 py-3 text-right text-gray-500 tabular-nums">{{ $p->current_stock }}</td><td class="px-6 py-3 text-right tabular-nums text-emerald-400 font-bold">0</td></tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada barang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
