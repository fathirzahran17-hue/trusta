<x-app-layout>
    @section('title', 'Aset Berdasarkan Lokasi')
    @section('subtitle', 'Daftar penyebaran aset tetap berdasarkan lokasi penyimpanannya')

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.fixed_assets.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Aset Tetap
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg text-sm transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak Laporan
        </button>
    </div>

    <style>
        @media print {
            body * { visibility: hidden; }
            #printableArea, #printableArea * { visibility: visible; }
            #printableArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border-radius: 0; background: white; color: black; }
            .no-print { display: none !important; }
            .print-text-black { color: black !important; }
            .print-border-black { border-color: black !important; }
        }
    </style>

    <div id="printableArea" class="space-y-8">
        @forelse($assets as $location => $locationAssets)
        <div class="bg-[#1E293B] border border-gray-800 print:border-black rounded-2xl shadow-xl overflow-hidden print:shadow-none print:rounded-none">
            <div class="px-6 py-4 bg-[#0F172A] border-b border-gray-800 print:bg-white print:border-black flex justify-between items-center">
                <h3 class="text-lg font-bold text-white print:text-black flex items-center">
                    <svg class="w-5 h-5 mr-2 text-trusta-teal print:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    {{ $location ?: '(Tidak ada lokasi)' }}
                </h3>
                <span class="px-3 py-1 bg-gray-800 text-gray-300 print:bg-transparent print:text-black text-sm rounded-full font-medium">
                    {{ count($locationAssets) }} Aset
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left print:text-black">
                    <thead class="bg-gray-800/50 text-gray-400 print:bg-transparent print:text-black uppercase text-xs">
                        <tr class="print:border-b print:border-black">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-6 py-3">Nama Aset</th>
                            <th class="px-6 py-3">Kategori</th>
                            <th class="px-6 py-3">Kondisi</th>
                            <th class="px-6 py-3 text-right">Nilai Buku</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800 print:divide-black">
                        @php $totalBookValue = 0; @endphp
                        @foreach($locationAssets as $asset)
                        <tr class="hover:bg-[#0F172A]/30 print:hover:bg-transparent transition-colors">
                            <td class="px-6 py-3 font-mono font-medium text-trusta-teal print:text-black">{{ $asset->code }}</td>
                            <td class="px-6 py-3 text-white print:text-black">{{ $asset->name }}</td>
                            <td class="px-6 py-3 text-gray-400 print:text-black">{{ $asset->category->name ?? '-' }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $kondisi = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'];
                                    $k = $kondisi[$asset->condition] ?? '?';
                                @endphp
                                <span class="text-gray-300 print:text-black">{{ $k }}</span>
                            </td>
                            <td class="px-6 py-3 text-right tabular-nums text-gray-300 print:text-black">
                                Rp {{ number_format($asset->book_value, 0, ',', '.') }}
                            </td>
                        </tr>
                        @php $totalBookValue += $asset->book_value; @endphp
                        @endforeach
                        <tr class="bg-gray-800/30 print:bg-transparent font-bold">
                            <td colspan="4" class="px-6 py-3 text-right text-gray-400 print:text-black">Total Nilai Buku di Lokasi Ini</td>
                            <td class="px-6 py-3 text-right text-white print:text-black">Rp {{ number_format($totalBookValue, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="bg-[#1E293B] rounded-2xl p-12 text-center border border-gray-800">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            <h3 class="text-xl font-medium text-white mb-2">Belum Ada Aset Aktif</h3>
            <p class="text-gray-400 max-w-md mx-auto">
                Laporan ini akan menampilkan daftar aset yang dikelompokkan berdasarkan lokasinya.
            </p>
        </div>
        @endforelse
    </div>
</x-app-layout>
