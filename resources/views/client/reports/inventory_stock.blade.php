<x-app-layout>
    @section('title', 'Kartu Stok Persediaan')
    @section('subtitle', 'Laporan sisa stok seluruh barang dagangan')

    <div class="mb-6">
        <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Laporan
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">SKU</th>
                    <th class="px-6 py-4">Nama Barang</th>
                    <th class="px-6 py-4">Kategori</th>
                    <th class="px-6 py-4">Satuan</th>
                    <th class="px-6 py-4 text-right">Stok Saat Ini</th>
                    <th class="px-6 py-4 text-right">Harga Beli</th>
                    <th class="px-6 py-4 text-right">Nilai Persediaan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @php $totalNilai = 0; @endphp
                @forelse($products as $p)
                @php $nilai = $p->current_stock * $p->buy_price; $totalNilai += $nilai; @endphp
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 font-mono text-trusta-teal font-semibold">{{ $p->sku }}</td>
                    <td class="px-6 py-3 text-white font-medium">{{ $p->name }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ $p->category->name ?? '-' }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ $p->unit ?? '-' }}</td>
                    <td class="px-6 py-3 text-right">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $p->current_stock > 0 ? 'bg-emerald-900/50 text-emerald-400' : 'bg-red-900/50 text-red-400' }}">
                            {{ $p->current_stock }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right text-gray-400 tabular-nums">Rp {{ number_format($p->buy_price, 0, ',', '.') }}</td>
                    <td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($nilai, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">Belum ada data barang.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-[#0F172A] font-bold text-white text-sm">
                <tr>
                    <td colspan="6" class="px-6 py-4 text-right uppercase">Total Nilai Persediaan</td>
                    <td class="px-6 py-4 text-right tabular-nums text-trusta-teal text-lg">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-app-layout>
