<x-app-layout>
    @section('title', 'Aset Tetap (Fixed Assets)')
    @section('subtitle', 'Daftar master data aset tetap perusahaan beserta rincian nilai penyusutan')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <form action="{{ route('client.fixed_assets.index') }}" method="GET" class="flex items-center gap-3">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode / nama aset..."
                    class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm pl-10 w-72">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('client.fixed_assets.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ !request('status') ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Semua</a>
                <a href="{{ route('client.fixed_assets.index', ['status' => 'aktif']) }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('status') === 'aktif' ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Aktif</a>
                <a href="{{ route('client.fixed_assets.index', ['status' => 'arsip']) }}" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('status') === 'arsip' ? 'bg-trusta-teal text-navy-900' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">Arsip</a>
            </div>
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('client.fixed_assets.depreciation') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg text-sm transition-colors shadow-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Jalankan Penyusutan
            </a>
            <a href="{{ route('client.fixed_assets.create') }}" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-semibold rounded-lg text-sm transition-colors shadow-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Aset Baru
            </a>
        </div>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Kode</th>
                        <th class="px-6 py-4">Nama Aset</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Tgl Perolehan</th>
                        <th class="px-6 py-4 text-right">Harga Perolehan</th>
                        <th class="px-6 py-4 text-right">Nilai Buku</th>
                        <th class="px-6 py-4 text-center">Kondisi</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($assets as $asset)
                    <tr class="hover:bg-[#0F172A]/50 transition-colors">
                        <td class="px-6 py-4 font-mono text-trusta-teal font-semibold">{{ $asset->code }}</td>
                        <td class="px-6 py-4">
                            <div class="text-white font-medium">{{ $asset->name }}</div>
                            @if($asset->location)
                                <div class="text-xs text-gray-500 mt-1">📍 {{ $asset->location }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-400">{{ $asset->category->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-gray-400">{{ $asset->acquisition_date->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 text-right font-medium text-white tabular-nums">Rp {{ number_format($asset->acquisition_cost, 2, ',', '.') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="font-medium text-white tabular-nums">Rp {{ number_format($asset->book_value, 2, ',', '.') }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <div class="w-full bg-gray-700 rounded-full h-1.5">
                                    <div class="bg-trusta-teal h-1.5 rounded-full" style="width: {{ $asset->depreciation_percent }}%"></div>
                                </div>
                                <span>Terdepresiasi {{ $asset->depreciation_percent }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $kondisi = ['baik' => ['Baik', 'bg-emerald-900/50 text-emerald-400'], 'rusak_ringan' => ['Rusak Ringan', 'bg-yellow-900/50 text-yellow-400'], 'rusak_berat' => ['Rusak Berat', 'bg-red-900/50 text-red-400']];
                                $k = $kondisi[$asset->condition] ?? ['?', 'bg-gray-700 text-gray-300'];
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $k[1] }}">{{ $k[0] }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($asset->status === 'aktif')
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-400">Aktif</span>
                            @elseif($asset->status === 'dijual')
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-900/50 text-blue-400">Dijual</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-700 text-gray-400">{{ ucfirst($asset->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('client.fixed_assets.edit', $asset) }}" class="text-trusta-teal hover:text-white transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('client.fixed_assets.destroy', $asset) }}" method="POST" onsubmit="return confirm('Yakin hapus aset ini?')">
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
                            <svg class="w-12 h-12 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <p class="text-gray-500 text-sm">Belum ada data aset tetap.</p>
                            <a href="{{ route('client.fixed_assets.create') }}" class="inline-block mt-4 px-4 py-2 bg-trusta-teal text-navy-900 font-semibold rounded-lg text-sm hover:bg-teal-400 transition-colors">
                                + Tambah Aset Pertama
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assets->hasPages())
            <div class="px-6 py-4 border-t border-gray-800">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
