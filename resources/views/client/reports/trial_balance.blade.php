<x-app-layout>
    @section('title', 'Neraca Saldo (Trial Balance)')
    @section('subtitle', 'Daftar saldo seluruh akun per tanggal ' . \Carbon\Carbon::parse($asOfDate)->format('d M Y'))

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Laporan
        </a>
        <form action="{{ route('client.reports.trial-balance') }}" method="GET" class="flex items-center gap-3">
            <label class="text-sm text-gray-400">Per Tanggal:</label>
            <input type="date" name="as_of_date" value="{{ $asOfDate }}" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
            <button class="px-4 py-2 bg-trusta-teal text-navy-900 font-semibold rounded-lg text-sm">Tampilkan</button>
        </form>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">Kode</th>
                    <th class="px-6 py-4">Nama Akun</th>
                    <th class="px-6 py-4">Tipe</th>
                    <th class="px-6 py-4 text-right">Saldo Debit</th>
                    <th class="px-6 py-4 text-right">Saldo Kredit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($trialData as $row)
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 font-mono text-trusta-teal">{{ $row['account']->code }}</td>
                    <td class="px-6 py-3 text-white font-medium">{{ $row['account']->name }}</td>
                    <td class="px-6 py-3 text-gray-400 capitalize">{{ $row['account']->type }}</td>
                    <td class="px-6 py-3 text-right text-white tabular-nums">{{ $row['saldo_debit'] > 0 ? 'Rp ' . number_format($row['saldo_debit'], 0, ',', '.') : '' }}</td>
                    <td class="px-6 py-3 text-right text-white tabular-nums">{{ $row['saldo_credit'] > 0 ? 'Rp ' . number_format($row['saldo_credit'], 0, ',', '.') : '' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Tidak ada data jurnal yang tercatat.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-[#0F172A] font-bold text-white">
                <tr>
                    <td colspan="3" class="px-6 py-4 text-right uppercase text-sm">Total</td>
                    <td class="px-6 py-4 text-right tabular-nums">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-right tabular-nums">Rp {{ number_format($totalCredit, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-app-layout>
