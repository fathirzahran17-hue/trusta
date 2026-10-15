<x-app-layout>
    @section('title', 'Pesanan Penjualan')
    @section('subtitle', 'Daftar penawaran & pesanan dari pelanggan')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A] flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Data Penawaran ({{ $quotes->total() }})</h3>
        </div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">No. Penawaran</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Pelanggan</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($quotes as $q)
                <tr class="hover:bg-[#0F172A]/50 transition-colors">
                    <td class="px-6 py-3 font-mono text-trusta-teal">{{ $q->quote_number }}</td>
                    <td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($q->quote_date)->format('d M Y') }}</td>
                    <td class="px-6 py-3 text-white">{{ $q->customer->name ?? '-' }}</td>
                    <td class="px-6 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold {{ $q->status === 'accepted' ? 'bg-emerald-900/50 text-emerald-400' : ($q->status === 'sent' ? 'bg-blue-900/50 text-blue-400' : 'bg-gray-700 text-gray-300') }}">{{ ucfirst($q->status) }}</span></td>
                    <td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($q->total, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data pesanan penjualan.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($quotes->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $quotes->links() }}</div> @endif
    </div>
</x-app-layout>
