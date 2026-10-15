<x-app-layout>
    @section('title', 'Penerimaan Penjualan')
    @section('subtitle', 'Pencatatan pembayaran dari pelanggan')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Data Penerimaan ({{ $receipts->total() }})</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Akun</th><th class="px-6 py-3">Keterangan</th><th class="px-6 py-3 text-right">Jumlah</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($receipts as $r)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 text-gray-400">{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td><td class="px-6 py-3 text-trusta-teal">{{ $r->account->name ?? '-' }}</td><td class="px-6 py-3 text-white">{{ $r->description }}</td><td class="px-6 py-3 text-right text-emerald-400 font-semibold tabular-nums">Rp {{ number_format($r->amount, 0, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Belum ada data penerimaan.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($receipts->hasPages()) <div class="px-6 py-4 border-t border-gray-800">{{ $receipts->links() }}</div> @endif
    </div>
</x-app-layout>
