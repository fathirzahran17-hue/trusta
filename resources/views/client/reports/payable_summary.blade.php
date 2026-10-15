<x-app-layout>
    @section('title', 'Rekap Hutang Pemasok')
    @section('subtitle', 'Total sisa hutang masing-masing pemasok')

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
                    <th class="px-6 py-4">No</th>
                    <th class="px-6 py-4">Nama Pemasok</th>
                    <th class="px-6 py-4 text-right">Total Hutang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($suppliers as $idx => $sup)
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 text-gray-500">{{ $idx + 1 }}</td>
                    <td class="px-6 py-3 text-white font-medium">{{ $sup->name }}</td>
                    <td class="px-6 py-3 text-right text-amber-400 font-bold tabular-nums">Rp {{ number_format($sup->total_hutang, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-6 py-10 text-center text-gray-500">Tidak ada hutang yang belum lunas.</td></tr>
                @endforelse
            </tbody>
            @if($grandTotal > 0)
            <tfoot class="bg-[#0F172A] font-bold text-white">
                <tr>
                    <td colspan="2" class="px-6 py-4 text-right uppercase text-sm">Grand Total</td>
                    <td class="px-6 py-4 text-right tabular-nums text-trusta-teal text-lg">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</x-app-layout>
