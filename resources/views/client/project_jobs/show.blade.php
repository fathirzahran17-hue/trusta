<x-app-layout>
    @section('title', 'Kartu Job: ' . $projectJob->name)
    @section('subtitle', 'Rincian jurnal yang ditandai dengan job ini')

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.project-jobs.index') }}" class="text-trusta-teal hover:underline text-sm">&larr; Kembali ke Kartu Job</a>
        <a href="{{ route('client.project-jobs.edit', $projectJob) }}" class="px-4 py-2 border border-gray-600 rounded-lg text-sm text-gray-300 hover:bg-gray-700">Edit</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
            <div class="text-xs text-gray-400">Pendapatan</div>
            <div class="text-xl font-bold text-green-400">Rp {{ number_format($income, 2, ',', '.') }}</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
            <div class="text-xs text-gray-400">Pengeluaran</div>
            <div class="text-xl font-bold text-red-400">Rp {{ number_format($expense, 2, ',', '.') }}</div>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
            <div class="text-xs text-gray-400">Laba / Rugi</div>
            <div class="text-xl font-bold {{ ($income - $expense) >= 0 ? 'text-green-400' : 'text-red-400' }}">Rp {{ number_format($income - $expense, 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl overflow-hidden">
        <div class="p-6 overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400">
                        <th class="border-b border-gray-700 p-3">Tanggal</th>
                        <th class="border-b border-gray-700 p-3">Jurnal</th>
                        <th class="border-b border-gray-700 p-3">Akun</th>
                        <th class="border-b border-gray-700 p-3 text-right">Debit</th>
                        <th class="border-b border-gray-700 p-3 text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($details as $d)
                        <tr>
                            <td class="border-b border-gray-700 p-3">{{ optional(optional($d->journal)->transaction_date)->format('d/m/Y') }}</td>
                            <td class="border-b border-gray-700 p-3">
                                @if($d->journal)
                                    <a href="{{ route('client.journals.show', $d->journal) }}" class="text-trusta-teal hover:underline">{{ $d->journal->ref_number }}</a>
                                @endif
                            </td>
                            <td class="border-b border-gray-700 p-3">{{ optional($d->account)->code }} {{ optional($d->account)->name }}</td>
                            <td class="border-b border-gray-700 p-3 text-right font-mono">{{ number_format($d->debit, 2, ',', '.') }}</td>
                            <td class="border-b border-gray-700 p-3 text-right font-mono">{{ number_format($d->credit, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-gray-400">Belum ada jurnal untuk job ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
