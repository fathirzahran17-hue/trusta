<x-app-layout>
    @section('title', 'Buku Besar (General Ledger)')
    @section('subtitle', 'Rincian mutasi per akun')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Laporan
        </a>
        <form action="{{ route('client.reports.general-ledger') }}" method="GET" class="flex flex-wrap items-center gap-3">
            <select name="account_id" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
                <option value="">Semua Akun</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ $accountId == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                @endforeach
            </select>
            <input type="date" name="start_date" value="{{ $startDate }}" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
            <span class="text-gray-500">s/d</span>
            <input type="date" name="end_date" value="{{ $endDate }}" class="bg-gray-800 border-gray-700 text-white rounded-lg text-sm">
            <button class="px-4 py-2 bg-trusta-teal text-navy-900 font-semibold rounded-lg text-sm">Filter</button>
        </form>
    </div>

    @forelse($ledgerData as $group)
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden mb-6">
        <div class="px-6 py-4 bg-[#0F172A] border-b border-gray-800">
            <h3 class="text-white font-semibold">{{ $group['account']->code }} — {{ $group['account']->name }}</h3>
            <p class="text-gray-500 text-xs capitalize">Tipe: {{ $group['account']->type }}</p>
        </div>
        <table class="w-full text-sm text-left">
            <thead class="text-gray-400 uppercase text-xs bg-[#0F172A]/50">
                <tr>
                    <th class="px-6 py-3">Tanggal</th>
                    <th class="px-6 py-3">No. Jurnal</th>
                    <th class="px-6 py-3">Keterangan</th>
                    <th class="px-6 py-3 text-right">Debit</th>
                    <th class="px-6 py-3 text-right">Kredit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @php $runDebit = 0; $runCredit = 0; @endphp
                @foreach($group['details'] as $d)
                @php $runDebit += $d->debit; $runCredit += $d->credit; @endphp
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($d->journal->transaction_date)->format('d M Y') }}</td>
                    <td class="px-6 py-3 text-trusta-teal font-mono text-xs">{{ $d->journal->ref_number }}</td>
                    <td class="px-6 py-3 text-gray-300">{{ $d->journal->description }}</td>
                    <td class="px-6 py-3 text-right text-white tabular-nums">{{ $d->debit > 0 ? 'Rp ' . number_format($d->debit, 0, ',', '.') : '' }}</td>
                    <td class="px-6 py-3 text-right text-white tabular-nums">{{ $d->credit > 0 ? 'Rp ' . number_format($d->credit, 0, ',', '.') : '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-[#0F172A] font-bold text-white text-sm">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-right">Total</td>
                    <td class="px-6 py-3 text-right tabular-nums">Rp {{ number_format($runDebit, 0, ',', '.') }}</td>
                    <td class="px-6 py-3 text-right tabular-nums">Rp {{ number_format($runCredit, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @empty
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-12 text-center">
        <p class="text-gray-500">Tidak ada mutasi yang ditemukan untuk periode ini.</p>
    </div>
    @endforelse
</x-app-layout>
