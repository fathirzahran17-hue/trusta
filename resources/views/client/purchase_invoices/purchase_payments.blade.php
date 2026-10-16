<x-app-layout>
    @section('title', 'Pembayaran Pembelian')
    @section('subtitle', 'Pengeluaran kas/bank (SP-) ke pemasok, tertaut ke jurnalnya')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A] flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Pembayaran ({{ $payments->total() }})</h3>
            <a href="{{ route('client.cash-bank.payments') }}" class="text-xs text-trusta-teal hover:underline">+ Catat Pembayaran</a>
        </div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">No. Jurnal</th><th class="px-6 py-3">Keterangan</th><th class="px-6 py-3">Akun</th><th class="px-6 py-3 text-right">Jumlah</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($payments as $j)
                @php $amount = $j->details->sum('credit'); @endphp
                <tr class="hover:bg-[#0F172A]/50">
                    <td class="px-6 py-3 text-gray-400">{{ optional($j->transaction_date)->format('d M Y') }}</td>
                    <td class="px-6 py-3"><a href="{{ route('client.journals.show', $j) }}" class="text-trusta-teal hover:underline">{{ $j->ref_number }}</a></td>
                    <td class="px-6 py-3 text-white">{{ $j->description }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ $j->details->where('credit', '>', 0)->map(fn($d) => optional($d->account)->name)->filter()->implode(', ') }}</td>
                    <td class="px-6 py-3 text-right text-red-400 font-semibold tabular-nums">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($payments->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $payments->links() }}</div> @endif
    </div>
</x-app-layout>
