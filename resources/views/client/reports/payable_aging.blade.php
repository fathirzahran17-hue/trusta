<x-app-layout>
    @section('title', 'Umur Hutang (Payable Aging)')
    @section('subtitle', 'Analisis hutang berdasarkan umur jatuh tempo per pemasok')

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
                    <th class="px-6 py-4">No. Faktur</th>
                    <th class="px-6 py-4">Pemasok</th>
                    <th class="px-6 py-4">Tgl Jatuh Tempo</th>
                    <th class="px-6 py-4 text-center">Umur</th>
                    <th class="px-6 py-4">Kategori</th>
                    <th class="px-6 py-4 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @php $total = 0; @endphp
                @forelse($agingData as $row)
                @php $total += $row['invoice']->grand_total; @endphp
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 font-mono text-trusta-teal">{{ $row['invoice']->invoice_number }}</td>
                    <td class="px-6 py-3 text-white">{{ $row['invoice']->supplier->name ?? '-' }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($row['invoice']->due_date)->format('d M Y') }}</td>
                    <td class="px-6 py-3 text-center">
                        @if($row['days_overdue'] > 0)
                            <span class="text-red-400 font-bold">{{ $row['days_overdue'] }} hari</span>
                        @else
                            <span class="text-emerald-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        @php $color = match($row['bucket']) { 'Belum Jatuh Tempo' => 'bg-emerald-900/50 text-emerald-400', '1-30 hari' => 'bg-amber-900/50 text-amber-400', '31-60 hari' => 'bg-orange-900/50 text-orange-400', default => 'bg-red-900/50 text-red-400' }; @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $color }}">{{ $row['bucket'] }}</span>
                    </td>
                    <td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($row['invoice']->grand_total, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Semua hutang telah lunas. 🎉</td></tr>
                @endforelse
            </tbody>
            @if(count($agingData) > 0)
            <tfoot class="bg-[#0F172A] font-bold text-white">
                <tr>
                    <td colspan="5" class="px-6 py-4 text-right uppercase text-sm">Total Hutang Belum Lunas</td>
                    <td class="px-6 py-4 text-right tabular-nums text-trusta-teal text-lg">Rp {{ number_format($total, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</x-app-layout>
