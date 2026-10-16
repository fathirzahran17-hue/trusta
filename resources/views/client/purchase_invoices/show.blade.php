<x-app-layout>
    @section('title', 'Detail Faktur Pembelian')
    @section('subtitle', $purchase_invoice->invoice_number)

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.purchase-invoices.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Tagihan
        </a>
        
        <div class="flex space-x-3">
            <button onclick="window.print()" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak (Print)
            </button>
            @if($purchase_invoice->journal)
                <a href="{{ route('client.journals.show', $purchase_invoice->journal_id) }}" class="px-4 py-2 bg-trusta-teal/10 border border-trusta-teal/50 text-trusta-teal hover:bg-trusta-teal hover:text-navy-900 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Lihat Jurnal Akuntansi
                </a>
            @endif
        </div>
    </div>

    <!-- Area yang bisa di-print -->
    <div id="printableArea" class="bg-white text-gray-900 shadow-2xl rounded-2xl overflow-hidden max-w-4xl mx-auto">
        <!-- Print Styles -->
        <style>
            @media print {
                body * { visibility: hidden; }
                #printableArea, #printableArea * { visibility: visible; }
                #printableArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; }
                .no-print { display: none !important; }
            }
        </style>

        <div class="p-10 md:p-12">
            <!-- Header Invoice -->
            <div class="flex justify-between items-start border-b border-gray-200 pb-8 mb-8">
                <div>
                    <h1 class="text-3xl font-poppins font-bold tracking-wider text-gray-900 mb-1">PURCHASE INVOICE</h1>
                    <p class="text-gray-500 font-medium">Internal Ref: {{ $purchase_invoice->invoice_number }}</p>
                    
                    <div class="mt-6">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Status Pembayaran</p>
                        @if($purchase_invoice->status == 'paid')
                            <span class="inline-block bg-green-100 text-green-700 font-bold px-3 py-1 rounded">LUNAS</span>
                        @else
                            <span class="inline-block bg-red-100 text-red-700 font-bold px-3 py-1 rounded">BELUM DIBAYAR</span>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xl font-poppins font-bold text-gray-900">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</div>
                    <div class="text-sm text-gray-500 mt-1 max-w-xs ml-auto">
                        <!-- Alamat perusahaan bisa ditaruh sini nanti -->
                        {{ auth()->user()->email }}
                    </div>
                </div>
            </div>

            <!-- Info Vendor -->
            <div class="flex justify-between items-start mb-8">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-2">Tagihan Dari Pemasok:</p>
                    <h3 class="text-lg font-bold text-gray-900">{{ $purchase_invoice->supplier->name }}</h3>
                    @if($purchase_invoice->supplier->company_name)
                        <p class="text-sm font-medium text-gray-600">{{ $purchase_invoice->supplier->company_name }}</p>
                    @endif
                    @if($purchase_invoice->supplier->address)
                        <p class="text-sm text-gray-500 mt-1 max-w-xs">{{ $purchase_invoice->supplier->address }}</p>
                    @endif
                    @if($purchase_invoice->supplier->email)
                        <p class="text-sm text-gray-500 mt-1">{{ $purchase_invoice->supplier->email }}</p>
                    @endif
                </div>
                <div class="text-right bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <div class="text-gray-500 font-medium text-left">No. Faktur Vendor:</div>
                        <div class="text-gray-900 font-bold">{{ $purchase_invoice->supplier_invoice_no ?: '-' }}</div>

                        <div class="text-gray-500 font-medium text-left">Tanggal Faktur:</div>
                        <div class="text-gray-900 font-bold">{{ $purchase_invoice->invoice_date->format('d F Y') }}</div>
                        
                        <div class="text-gray-500 font-medium text-left">Jatuh Tempo:</div>
                        <div class="text-gray-900 font-bold">{{ $purchase_invoice->due_date->format('d F Y') }}</div>
                    </div>
                </div>
            </div>

            <!-- Tabel Item -->
            <table class="w-full text-sm text-left mb-8">
                <thead class="text-xs uppercase text-gray-500 border-b-2 border-gray-900">
                    <tr>
                        <th class="py-3 px-2 font-bold">Produk / Layanan</th>
                        <th class="py-3 px-2 text-right font-bold w-20">Qty</th>
                        <th class="py-3 px-2 text-center font-bold w-16">Satuan</th>
                        <th class="py-3 px-2 text-right font-bold w-32">Harga Beli</th>
                        <th class="py-3 px-2 text-right font-bold w-16">Disk</th>
                        <th class="py-3 px-2 text-right font-bold w-32">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($purchase_invoice->items as $item)
                        <tr>
                            <td class="py-4 px-2">
                                <div class="font-bold text-gray-900">{{ $item->product->name }}</div>
                                @if($item->description)
                                    <div class="text-gray-500 text-xs mt-1">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="py-4 px-2 text-right text-gray-600">{{ (float)$item->quantity }}</td>
                            <td class="py-4 px-2 text-center text-gray-600">{{ $item->product->unit ?? 'pcs' }}</td>
                            <td class="py-4 px-2 text-right text-gray-600">Rp {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="py-4 px-2 text-right text-gray-600">{{ $item->discount_percent > 0 ? (float)$item->discount_percent . '%' : '-' }}</td>
                            <td class="py-4 px-2 text-right font-medium text-gray-900">Rp {{ number_format($item->total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Kalkulasi Total -->
            <div class="flex justify-end border-t border-gray-200 pt-6">
                <div class="w-1/2">
                    <div class="flex justify-between py-2 text-sm">
                        <span class="text-gray-500 font-medium">Subtotal</span>
                        <span class="text-gray-900 font-medium">Rp {{ number_format($purchase_invoice->subtotal, 2, ',', '.') }}</span>
                    </div>
                    @if($purchase_invoice->discount_amount > 0)
                        <div class="flex justify-between py-2 text-sm">
                            <span class="text-gray-500 font-medium">Diskon ({{ (float)$purchase_invoice->discount_percent }}%)</span>
                            <span class="text-red-600 font-medium">-Rp {{ number_format($purchase_invoice->discount_amount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($purchase_invoice->tax_amount > 0)
                        <div class="flex justify-between py-2 text-sm border-b border-gray-200">
                            <span class="text-gray-500 font-medium">Pajak ({{ $purchase_invoice->tax ? $purchase_invoice->tax->name : 'Tax' }} {{ (float)$purchase_invoice->tax_percent }}%)</span>
                            <span class="text-gray-900 font-medium">Rp {{ number_format($purchase_invoice->tax_amount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between py-4 border-t-2 border-gray-900 mt-2">
                        <span class="text-lg font-bold text-gray-900 uppercase tracking-wider">Total Hutang</span>
                        <span class="text-2xl font-bold text-gray-900">Rp {{ number_format($purchase_invoice->total, 2, ',', '.') }}</span>
                    </div>
                    @if($purchase_invoice->amount_paid > 0)
                        <div class="flex justify-between py-2 text-sm">
                            <span class="text-gray-500 font-medium">Sudah Dibayar</span>
                            <span class="text-green-600 font-medium">Rp {{ number_format($purchase_invoice->amount_paid, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between py-2 text-sm border-t border-gray-200">
                            <span class="text-gray-500 font-bold">Sisa Hutang</span>
                            <span class="text-red-600 font-bold">Rp {{ number_format($purchase_invoice->amount_due, 2, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Notes -->
            @if($purchase_invoice->notes)
            <div class="mt-12 pt-8 border-t border-gray-200">
                <h4 class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-2">Catatan Internal</h4>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $purchase_invoice->notes }}</p>
            </div>
            @endif
            
            <div class="mt-16 text-center text-xs text-gray-400">
                <p>Dicetak sebagai Bukti Pencatatan Hutang Internal.</p>
                <p>Sistem TRUSTA - Trusted Accounting Solution.</p>
            </div>
        </div>
    </div>
    <div class="max-w-4xl mx-auto mt-6">
        @include('partials.related-docs', ['groups' => \App\Services\DocumentLinker::relatedForPurchaseInvoice($purchase_invoice)])
    </div>
</x-app-layout>
