<x-app-layout>
    @section('title', 'Disposisi Aset Tetap')
    @section('subtitle', 'Pencatatan penjualan atau pelepasan aset tetap')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Daftar Aset ({{ $assets->count() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Nama Aset</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Nilai Perolehan</th><th class="px-6 py-3 text-right">Nilai Buku</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($assets as $a)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $a->code }}</td><td class="px-6 py-3 text-white font-medium">{{ $a->name }}</td><td class="px-6 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold {{ $a->status === 'aktif' ? 'bg-emerald-900/50 text-emerald-400' : 'bg-red-900/50 text-red-400' }}">{{ ucfirst($a->status) }}</span></td><td class="px-6 py-3 text-right text-gray-400 tabular-nums">Rp {{ number_format($a->acquisition_cost, 2, ',', '.') }}</td><td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($a->book_value, 2, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Tidak ada aset.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
