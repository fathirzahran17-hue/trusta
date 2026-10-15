<x-app-layout>
    @section('title', 'Pengiriman Pesanan')
    @section('subtitle', 'Daftar surat jalan dan pengiriman barang ke pelanggan')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Data Pengiriman ({{ $invoices->total() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">No. Faktur</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Pelanggan</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($invoices as $inv)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $inv->invoice_number }}</td><td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') }}</td><td class="px-6 py-3 text-white">{{ $inv->customer->name ?? '-' }}</td><td class="px-6 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-emerald-900/50 text-emerald-400">Terkirim</span></td><td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($inv->grand_total, 0, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data pengiriman.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($invoices->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $invoices->links() }}</div> @endif
    </div>
</x-app-layout>
