<x-app-layout>
    @section('title', 'Pesanan Penjualan')
    @section('subtitle', 'Kelola pesanan (Sales Orders) dari pelanggan')

    <div style="display: flex; justify-content: flex-end; margin-bottom: 14px; gap: 8px;">
        <button onclick="exportToExcel('table-sales-orders', 'Pesanan_Penjualan')" class="bg-green-600 hover:bg-green-500 text-white text-xs font-semibold py-2 px-3 rounded inline-flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export Excel
        </button>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold py-2 px-3 rounded inline-flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak / PDF
        </button>
        <a href="{{ route('client.sales.orders.create') }}" class="btn-primary">
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Buat Pesanan Baru
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
            <table id="table-sales-orders" class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No. Pesanan</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Pelanggan</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Total</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $order->order_number }}
                                @if($order->customer_po_number)
                                <div style="font-size: 0.65rem; color: #475569;">PO Cust: {{ $order->customer_po_number }}</div>
                                @endif
                                @if($order->deliveryOrders->count() > 0)
                                    <div style="font-size: 0.65rem; color: #10b981; margin-top: 4px;">
                                        DO: 
                                        @foreach($order->deliveryOrders as $do)
                                            <span style="background: rgba(16,185,129,0.1); padding: 1px 4px; border-radius: 4px; border: 1px solid rgba(16,185,129,0.2);">{{ $do->delivery_number }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-white">{{ $order->customer->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ $order->order_date->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-trusta-teal" style="font-variant-numeric: tabular-nums;">
                                Rp {{ number_format($order->total, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($order->status == 'closed')
                                    <span class="badge badge-success">Selesai</span>
                                @elseif($order->status == 'open')
                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; background: rgba(59,130,246,0.1); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2);">Terbuka</span>
                                @elseif($order->status == 'cancelled')
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
                                <p style="font-size: 0.82rem;">Belum ada pesanan penjualan. Silakan buat baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($orders->hasPages())
        <div class="px-6 py-3 border-t border-gray-700/50">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
