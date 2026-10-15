<x-app-layout>
    @section('title', 'Pengiriman Pesanan (Delivery Orders)')
    @section('subtitle', 'Pantau pengiriman barang ke pelanggan Anda')

    <div style="display: flex; justify-content: flex-end; margin-bottom: 14px; gap: 8px;">
        <button onclick="exportToExcel('table-delivery-orders', 'Pengiriman_Pesanan')" class="bg-green-600 hover:bg-green-500 text-white text-xs font-semibold py-2 px-3 rounded inline-flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export Excel
        </button>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold py-2 px-3 rounded inline-flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak / PDF
        </button>
        <a href="{{ route('client.sales.deliveries.create') }}" class="btn-primary">
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Buat Pengiriman
        </a>
    </div>

    <script>
        function exportToExcel(tableId, filename) {
            let table = document.getElementById(tableId);
            let html = table.outerHTML;
            let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = filename + '.xls';
            a.click();
        }
    </script>

    @if(session('success'))
        <div class="mb-3 bg-green-500/10 border-l-3 border-green-500 p-3 text-green-400 rounded-r-lg text-xs">
            {{ session('success') }}
        </div>
    @endif

    <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
        <div class="overflow-x-auto">
            <table id="table-delivery-orders" class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No. DO</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Pelanggan</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tgl Pengiriman</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Terkait SO</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($deliveries as $do)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $do->delivery_number }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-white">{{ $do->customer->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ $do->delivery_date->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $do->salesOrder->order_number ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($do->status == 'shipped')
                                    <span class="badge badge-success">Dikirim</span>
                                @elseif($do->status == 'delivered')
                                    <span class="badge badge-success" style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2);">Diterima</span>
                                @elseif($do->status == 'cancelled')
                                    <span class="badge badge-danger">Dibatalkan</span>
                                @else
                                    <span class="badge" style="background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2);">Draft</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 40px 14px; text-align: center; color: #64748b;">
                                <svg style="width: 36px; height: 36px; margin: 0 auto 10px; color: #334155;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p style="font-size: 0.82rem;">Belum ada pengiriman barang. Silakan buat baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($deliveries->hasPages())
        <div class="px-6 py-3 border-t border-gray-700/50">
            {{ $deliveries->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
