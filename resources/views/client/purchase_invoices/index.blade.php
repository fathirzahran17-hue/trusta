<x-app-layout>
    @section('title', 'Faktur Pembelian (Purchase Invoices)')
    @section('subtitle', 'Catat tagihan dari pemasok dan pantau hutang usaha Anda')

    <div class="flex justify-end items-center mb-6 gap-3">
        <button onclick="exportToExcel('table-purchase-invoices', 'Faktur_Pembelian')" class="px-4 py-2 bg-green-600 text-white font-medium rounded-lg text-sm hover:bg-green-500 transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export Excel
        </button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg text-sm hover:bg-blue-500 transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak / PDF
        </button>
        <a href="{{ route('client.purchases.settlement') }}" class="rpt-btn">Status Pelunasan</a>
        <a href="{{ route('client.purchase-invoices.create', ['mode' => 'biaya']) }}" class="rpt-btn">Catat Biaya (tanpa produk)</a>
        <a href="{{ route('client.purchase-invoices.create') }}" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Catat Tagihan Baru
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
        <div class="mb-4 bg-green-500/10 border-l-4 border-green-500 p-4 text-green-400 rounded-r-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-gray-800/40 border border-gray-700/50 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table id="table-purchase-invoices" class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 rounded-tl-xl font-semibold">No. Internal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">No. Faktur Pemasok</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Pemasok</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tgl Faktur</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Total</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Sisa</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-center">Status</th>
                        <th scope="col" class="px-6 py-4 text-center rounded-tr-xl font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="px-6 py-4 text-gray-400">
                                {{ $invoice->supplier_invoice_no ?: '-' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-white">{{ $invoice->supplier->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ $invoice->invoice_date->format('d M Y') }}
                                <div class="text-xs text-gray-500">Jatuh tempo: {{ $invoice->due_date->format('d M Y') }}</div>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-trusta-teal">
                                Rp {{ number_format($invoice->total, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right" style="font-variant-numeric: tabular-nums;">{{ $invoice->status === 'void' ? '-' : 'Rp ' . number_format($invoice->amount_due, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center">
                                @include('partials.invoice-status', ['invoice' => $invoice])
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('client.purchase-invoices.show', $invoice->id) }}" class="text-trusta-teal hover:text-white transition-colors font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                <p>Belum ada tagihan pembelian. Silakan catat baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
