<x-app-layout>
    @section('title', 'Pindah Aset')
    @section('subtitle', 'Mutasi atau pemindahan lokasi aset tetap antar departemen/cabang')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Aset Aktif ({{ $assets->count() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Nama Aset</th><th class="px-6 py-3">Kategori</th><th class="px-6 py-3">Lokasi</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($assets as $a)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $a->code }}</td><td class="px-6 py-3 text-white font-medium">{{ $a->name }}</td><td class="px-6 py-3 text-gray-400">{{ $a->category->name ?? '-' }}</td><td class="px-6 py-3 text-gray-400">{{ $a->location ?? 'Belum ditentukan' }}</td></tr>
                @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Tidak ada aset aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
