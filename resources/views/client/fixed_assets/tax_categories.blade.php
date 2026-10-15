<x-app-layout>
    @section('title', 'Kategori Aset Tetap Pajak')
    @section('subtitle', 'Pengelompokkan aset berdasarkan tarif dan golongan penyusutan fiskal')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Kategori Pajak ({{ $categories->count() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">No</th><th class="px-6 py-3">Nama Kategori</th><th class="px-6 py-3">Tipe</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($categories as $idx => $c)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 text-gray-500">{{ $idx + 1 }}</td><td class="px-6 py-3 text-white font-medium">{{ $c->name }}</td><td class="px-6 py-3 text-gray-400 capitalize">{{ $c->depreciation_method ?? 'Garis Lurus' }}</td></tr>
                @empty
                <tr><td colspan="3" class="px-6 py-10 text-center text-gray-500">Belum ada kategori pajak.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
