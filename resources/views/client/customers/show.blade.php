<x-app-layout>
    @section('title', 'Detail Riwayat Piutang: ' . $customer->name)
    @section('subtitle', 'Laporan transaksi dan sisa piutang untuk pelanggan ini')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <form id="filterForm" action="{{ route('client.customers.show', $customer->id) }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Dari:</span>
                <input type="date" name="start_date" value="{{ $startDate }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Sampai:</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <button type="submit" class="btn-secondary">
                Terapkan
            </button>
        </form>

        <div class="flex space-x-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="rpt-btn excel">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Excel
            </button>
            <button type="submit" form="filterForm" name="export" value="csv" class="rpt-btn">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                CSV
            </button>
            <button type="submit" form="filterForm" formtarget="_blank" name="export" value="pdf" class="rpt-btn pdf">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                PDF
            </button>
            <a href="{{ route('client.customers.index') }}" class="btn-secondary ml-2">
                Kembali
            </a>
        </div>
    </div>

    <!-- Info Pelanggan -->
    <div class="bg-gray-800/40 border border-gray-700/50 rounded-2xl p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h3 class="text-xl font-bold text-white">{{ $customer->name }}</h3>
                <p class="text-gray-400 mt-1">{{ $customer->company_name ?? 'Personal' }} | {{ $customer->phone ?? '-' }} | {{ $customer->email ?? '-' }}</p>
                <p class="text-sm text-gray-500 mt-2">{{ $customer->address ?? 'Alamat tidak tersedia' }}</p>
            </div>
            <div class="mt-4 md:mt-0 text-right">
                <div class="text-sm text-gray-400 mb-1">Total Saldo Piutang (Aktif)</div>
                <div class="text-3xl font-bold text-trusta-teal">Rp {{ \App\Support\Money::fmt($totalPiutang) }}</div>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Transaksi -->
    <div class="bg-gray-800/40 border border-gray-700/50 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-gray-700/50 bg-gray-800/60 flex justify-between items-center">
            <h4 class="font-semibold text-gray-300">Riwayat Penjualan</h4>
            <span class="text-xs text-gray-400">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-900/50 text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">No. Invoice</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Status</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Total Transaksi</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Sudah Dibayar</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Sisa (Amount Due)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @php $sumTotal = 0; $sumPaid = 0; $sumDue = 0; @endphp
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4">{{ $inv->invoice_date->format('d/m/Y') }}</td>
                            <td class="px-6 py-4 font-medium text-white">
                                <a href="{{ route('client.sales-invoices.show', $inv->id) }}" class="text-trusta-teal hover:underline" target="_blank">{{ $inv->invoice_number }}</a>
                            </td>
                            <td class="px-6 py-4">
                                @if($inv->status == 'paid')
                                    <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs px-2.5 py-1 rounded-full">Lunas</span>
                                @elseif($inv->status == 'partially_paid')
                                    <span class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 text-xs px-2.5 py-1 rounded-full">Dibayar Sebagian</span>
                                @else
                                    <span class="bg-red-500/10 text-red-400 border border-red-500/20 text-xs px-2.5 py-1 rounded-full">Belum Dibayar</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">Rp {{ \App\Support\Money::fmt($inv->total) }}</td>
                            <td class="px-6 py-4 text-right">Rp {{ \App\Support\Money::fmt($inv->amount_paid) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-trusta-teal">Rp {{ \App\Support\Money::fmt($inv->amount_due) }}</td>
                        </tr>
                        @php
                            $sumTotal += $inv->total;
                            $sumPaid += $inv->amount_paid;
                            $sumDue += $inv->amount_due;
                        @endphp
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                <p>Tidak ada transaksi pada periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                    
                    @if(count($invoices) > 0)
                    <tr class="bg-gray-900/50 font-bold border-t-2 border-gray-700">
                        <td colspan="3" class="px-6 py-4 text-right text-white">TOTAL PERIODE INI</td>
                        <td class="px-6 py-4 text-right text-white">Rp {{ \App\Support\Money::fmt($sumTotal) }}</td>
                        <td class="px-6 py-4 text-right text-emerald-400">Rp {{ \App\Support\Money::fmt($sumPaid) }}</td>
                        <td class="px-6 py-4 text-right text-trusta-teal">Rp {{ \App\Support\Money::fmt($sumDue) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
