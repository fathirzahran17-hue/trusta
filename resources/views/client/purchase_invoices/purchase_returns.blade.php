<x-app-layout>
    @section('title', 'Retur Pembelian')
    @section('subtitle', 'Pencatatan pengembalian barang ke pemasok')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Data Retur ({{ $returns->total() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">No. Retur</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">No. Faktur</th><th class="px-6 py-3">Alasan</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($returns as $r)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $r->return_number }}</td><td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($r->return_date)->format('d M Y') }}</td><td class="px-6 py-3 text-white">{{ $r->purchaseInvoice->invoice_number ?? '-' }}</td><td class="px-6 py-3 text-gray-400">{{ $r->reason ?? '-' }}</td><td class="px-6 py-3 text-right text-red-400 font-semibold tabular-nums">Rp {{ number_format($r->total, 0, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data retur pembelian.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($returns->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $returns->links() }}</div> @endif
    </div>
</x-app-layout>
