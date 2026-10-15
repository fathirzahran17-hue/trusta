<x-app-layout>
    @section('title', 'Laporan Pembelian')
    @section('subtitle', 'Rekapitulasi faktur pembelian')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Laporan
        </a>
        <form action="{{ route('client.reports.purchase-report') }}" method="GET" class="flex items-center gap-3">
            <input type="date" name="start_date" value="{{ $startDate }}" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
            <span class="text-gray-500">s/d</span>
            <input type="date" name="end_date" value="{{ $endDate }}" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
            <button class="px-4 py-2 bg-trusta-teal text-navy-900 font-semibold rounded-lg text-sm">Filter</button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-5">
            <p class="text-gray-400 text-xs uppercase">Total Pembelian</p>
            <p class="text-2xl font-bold text-white mt-1">Rp {{ number_format($totalAmount, 0, ',', '.') }}</p>
        </div>
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-5">
            <p class="text-gray-400 text-xs uppercase">Sudah Dibayar</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1">Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
        </div>
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-5">
            <p class="text-gray-400 text-xs uppercase">Belum Dibayar</p>
            <p class="text-2xl font-bold text-amber-400 mt-1">Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">No. Faktur</th>
                    <th class="px-6 py-4">Tanggal</th>
                    <th class="px-6 py-4">Pemasok</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($invoices as $inv)
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 font-mono text-trusta-teal">{{ $inv->invoice_number }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') }}</td>
                    <td class="px-6 py-3 text-white">{{ $inv->supplier->name ?? '-' }}</td>
                    <td class="px-6 py-3">
                        @if($inv->status === 'paid')
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-emerald-900/50 text-emerald-400">Lunas</span>
                        @else
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-amber-900/50 text-amber-400">{{ ucfirst($inv->status) }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($inv->grand_total, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada faktur pembelian di periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
