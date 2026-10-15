<x-app-layout>
    @section('title', 'Penerimaan Barang')
    @section('subtitle', 'Daftar barang yang diterima dari pemasok')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Data Penerimaan ({{ $orders->total() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">No. PO</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Pemasok</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($orders as $o)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $o->po_number }}</td><td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($o->order_date)->format('d M Y') }}</td><td class="px-6 py-3 text-white">{{ $o->supplier->name ?? '-' }}</td><td class="px-6 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-emerald-900/50 text-emerald-400">Diterima</span></td><td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format($o->total, 0, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data penerimaan barang.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($orders->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $orders->links() }}</div> @endif
    </div>
</x-app-layout>
