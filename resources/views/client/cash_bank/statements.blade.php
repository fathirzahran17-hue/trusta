<x-app-layout>
    @section('title', 'Rekening Koran')
    @section('subtitle', 'Mutasi rekening bank dan kas')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col h-full">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Mutasi Rekening ({{ $transactions->total() }})</h3></div>
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-sm text-left">
                <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Akun</th>
                        <th class="px-6 py-3">Keterangan</th>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($transactions as $t)
                    @php
                        $isMasuk = $t->debit > 0;
                        $amount = $isMasuk ? $t->debit : $t->credit;
                        $date = $t->journal ? $t->journal->transaction_date : $t->created_at;
                        $desc = $t->description ?: ($t->journal ? $t->journal->description : '-');
                    @endphp
                    <tr class="hover:bg-[#0F172A]/50">
                        <td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</td>
                        <td class="px-6 py-3 text-trusta-teal font-medium">{{ $t->account->name ?? '-' }}</td>
                        <td class="px-6 py-3 text-gray-300">{{ $desc }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $isMasuk ? 'bg-emerald-900/50 text-emerald-400 border border-emerald-800/50' : 'bg-red-900/50 text-red-400 border border-red-800/50' }}">
                                {{ $isMasuk ? 'Masuk' : 'Keluar' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold tabular-nums {{ $isMasuk ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $isMasuk ? '+' : '-' }} Rp {{ number_format($amount, 2, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Belum ada data mutasi kas/bank.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages()) <div class="px-6 py-4 border-t border-gray-800 bg-[#0F172A]">{{ $transactions->links() }}</div> @endif
    </div>
</x-app-layout>
