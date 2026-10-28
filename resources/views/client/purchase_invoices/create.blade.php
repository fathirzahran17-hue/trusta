<x-app-layout>
    @php
        $__acctOpts = $expenseAccounts->map(fn ($a) => '<option value="a' . $a->id . '" data-price="0" data-unit="-" data-type="account">' . e($a->code . ' - ' . $a->name) . '</option>')->implode('');
    @endphp
    @section('title', $mode === 'biaya' ? 'Catat Biaya / Tagihan Pemasok' : 'Catat Faktur Pembelian Baru')
    @section('subtitle', $mode === 'biaya' ? 'Catat tagihan biaya (listrik, sewa, jasa, dll.) langsung ke akun biaya, tanpa memilih produk' : 'Pencatatan tagihan masuk yang otomatis membukukan Jurnal Akuntansi')

    <div class="mb-6">
        <a href="{{ route('client.purchase-invoices.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Tagihan
        </a>
    </div>

    @if(session('error'))
        <div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg">
            {{ session('error') }}
        </div>
    @endif

    @include('partials.pull-multi', ['label' => 'Tarik dari Penerimaan Barang / Pesanan Pembelian (PO)', 'partyWord' => 'pemasok', 'options' => array_merge($receipts, $sourceOrders), 'checked' => $checked, 'baseUrl' => route('client.purchase-invoices.create'), 'sourceText' => $prefill['source'], 'notice' => $prefill['ignored'] ? $prefill['ignored'] . ' dokumen dilewati karena beda pemasok.' : (($prefill['source'] && empty($prefill['rows'])) ? 'Semua barang pada dokumen yang dipilih sudah ditagih.' : null)])

    <form action="{{ route('client.purchase-invoices.store') }}" method="POST" id="invoiceForm">
        @csrf
        
        <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden mb-6">
            <div class="p-6 md:p-8 border-b border-gray-700/50">
                <div class="flex justify-between items-start mb-6">
                    <h3 class="text-lg font-medium text-white">Informasi Pemasok & Dokumen</h3>
                    <div class="text-right">
                        <span class="text-sm text-gray-400 block mb-1">Nomor Internal (Auto)</span>
                        <span class="text-xl font-bold text-trusta-teal">{{ $invoiceNumber }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-300 mb-2">Pemasok (Supplier) <span class="text-red-400">*</span></label>
                        <select name="supplier_id" id="supplier_id" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="">-- Pilih Pemasok --</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" {{ old('supplier_id', $prefill['party_id']) == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="supplier_invoice_no" class="block text-sm font-medium text-gray-300 mb-2">No. Faktur Pemasok</label>
                        <input type="text" name="supplier_invoice_no" id="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: INV-VEN-001">
                        <p class="text-xs text-gray-500 mt-1">Sesuai nota fisik/PDF dari vendor.</p>
                    </div>
                    
                    <div>
                        <label for="invoice_date" class="block text-sm font-medium text-gray-300 mb-2">Tanggal Faktur <span class="text-red-400">*</span></label>
                        <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                    </div>

                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-300 mb-2">Jatuh Tempo <span class="text-red-400">*</span></label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Detail Barang/Jasa -->
            <div class="p-6 md:p-8 bg-gray-900/50">
                <h3 class="text-lg font-medium text-white mb-1">{{ $mode === 'biaya' ? 'Rincian Biaya / Akun Lawan' : 'Item Produk / Layanan / Biaya yang Dibeli' }}</h3>
                <p class="text-xs text-gray-400 mb-4">Pilih <b>produk/jasa</b>, atau pilih <b>akun biaya</b> langsung (mis. Biaya Listrik) bila yang dicatat bukan barang. Jurnal: Debit akun yang dipilih, Kredit Hutang Usaha. Satu faktur boleh mencampur keduanya.</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left" id="itemsTable">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 w-1/4">{{ $mode === 'biaya' ? 'Akun Biaya / Produk' : 'Produk / Jasa / Akun Biaya' }}</th>
                                <th class="px-4 py-3" style="width: 16%;">Keterangan</th>
                                <th class="px-4 py-3 w-20 text-right">Qty</th>
                                <th class="px-4 py-3 text-center" style="width: 50px;">Satuan</th>
                                <th class="px-4 py-3 text-right" style="min-width: 170px;">Harga Satuan</th>
                                <th class="px-4 py-3 text-right" style="width: 70px;">Disk (%)</th>
                                <th class="px-4 py-3 w-36 text-right">Jumlah</th>
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="group item-row">
                                <td class="px-2 py-3">
                                                                        <select name="products[]" class="w-full text-sm bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm product-select" style="min-width: 230px;" required>
                                        <option value="" data-price="0" data-unit="">{{ $mode === 'biaya' ? '-- Pilih Akun Biaya --' : '-- Pilih Produk / Akun Biaya --' }}</option>
                                        @if($mode === 'biaya')<optgroup label="Akun Biaya / Akun Lawan">{!! $__acctOpts !!}</optgroup>@endif
                                        <optgroup label="Produk / Jasa">
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" data-price="{{ $prod->buy_price }}" data-unit="{{ $prod->unit }}" data-type="{{ $prod->type }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>
                                        @endforeach
                                        </optgroup>
                                        @if($mode !== 'biaya')<optgroup label="Akun Biaya / Akun Lawan">{!! $__acctOpts !!}</optgroup>@endif
                                    </select>
                                </td>
                                <td class="px-2 py-3">
                                    <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="hidden" name="row_docs[]" class="input-row-doc" value=""><input type="text" name="row_descriptions[]" class="w-full text-sm bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" placeholder="Opsional">
                                </td>
                                <td class="px-2 py-3">
                                    <input type="number" step="0.01" name="quantities[]" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-qty" value="1" min="0.01" required>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <span class="row-unit text-gray-400 text-xs">pcs</span>
                                </td>
                                <td class="px-2 py-3 relative">
                                    <input type="hidden" name="prices[]" class="input-price" value="0">
                                    <input type="text" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                                </td>
                                <td class="px-2 py-3">
                                    <input type="number" step="0.01" name="discounts[]" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-discount" value="0" min="0" max="100">
                                </td>
                                <td class="px-2 py-3 text-right font-medium text-white row-total">
                                    0.00
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <button type="button" class="text-gray-500 hover:text-red-400 transition-colors btn-remove-row" disabled>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-gray-700 bg-gray-800/30">
                            <tr>
                                <td colspan="3" class="px-4 py-4">
                                    <button type="button" id="btnAddRow" class="text-trusta-teal hover:text-white text-sm font-medium flex items-center transition-colors">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Baris
                                    </button>
                                </td>
                                <td colspan="3" class="px-4 py-3 text-right text-gray-400 font-medium">Subtotal</td>
                                <td class="px-4 py-3 text-right font-bold text-white text-lg" id="displaySubtotal">0,00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="3" class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-gray-400 font-medium">Diskon Keseluruhan (%)</span>
                                        <input type="number" step="0.01" name="discount_percent" id="discount_percent" value="0" min="0" max="100" class="w-20 text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" onchange="calculateTotals()" onkeyup="calculateTotals()">
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-yellow-400 text-lg" id="displayDiscount">0,00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="3" class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end">
                                        <span class="text-gray-400 font-medium mr-3">Pajak (Tax)</span>
                                        <select name="tax_id" id="tax_id" class="w-40 text-sm bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                                            <option value="" data-rate="0">Tanpa Pajak</option>
                                            @foreach($taxes as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}">{{ $tax->name }} ({{ (float)$tax->rate }}%)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-red-400 text-lg" id="displayTax">0,00</td>
                                <td></td>
                            </tr>
                            <tr class="bg-gray-800/80">
                                <td colspan="3"></td>
                                <td colspan="3" class="px-4 py-5 text-right text-white font-bold text-xl uppercase tracking-wider">Grand Total</td>
                                <td class="px-4 py-5 text-right font-bold text-trusta-teal text-2xl" id="displayGrandTotal">0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-8">
                    <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="notes" rows="3" class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" placeholder="Catatan internal terkait pembelian ini"></textarea>
                </div>
            </div>

            <div class="px-8 py-5 bg-gray-800/50 border-t border-gray-700/50 flex justify-between items-center">
                <div class="text-xs text-gray-500">
                    <span class="text-trusta-teal font-semibold">INFO:</span> Menyimpan invoice akan secara otomatis membuat Jurnal Hutang (AP) & Biaya.
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('client.purchase-invoices.index') }}" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium">
                        Batal
                    </a>
                    <button type="submit" id="btnSubmit" class="px-6 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
                        Simpan & Bukukan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('itemsBody');
            const btnAddRow = document.getElementById('btnAddRow');
            const taxSelect = document.getElementById('tax_id');
            const discountInput = document.getElementById('discount_percent');
            const displaySubtotal = document.getElementById('displaySubtotal');
            const displayDiscount = document.getElementById('displayDiscount');
            const displayTax = document.getElementById('displayTax');
            const displayGrandTotal = document.getElementById('displayGrandTotal');

            const productOptions = `{!! ($mode === 'biaya' ? '<optgroup label="Akun Biaya / Akun Lawan">' . $__acctOpts . '</optgroup>' : '') !!}<optgroup label="Produk / Jasa">@foreach($products as $prod)<option value="{{ $prod->id }}" data-price="{{ $prod->buy_price }}" data-unit="{{ $prod->unit }}" data-type="{{ $prod->type }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>@endforeach</optgroup>{!! ($mode !== 'biaya' ? '<optgroup label="Akun Biaya / Akun Lawan">' . $__acctOpts . '</optgroup>' : '') !!}`;

            window.calculateTotals = function() {
                let subtotal = 0;
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.input-price').value) || 0;
                    const discPct = parseFloat(row.querySelector('.input-discount').value) || 0;
                    const lineTotal = qty * price;
                    const discountAmt = lineTotal * (discPct / 100);
                    const total = lineTotal - discountAmt;
                    
                    row.querySelector('.row-total').textContent = total.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    subtotal += total;
                });

                const overallDiscPct = parseFloat(discountInput.value) || 0;
                const overallDiscAmt = subtotal * (overallDiscPct / 100);
                const afterDiscount = subtotal - overallDiscAmt;

                const selectedTax = taxSelect.options[taxSelect.selectedIndex];
                const taxRate = selectedTax ? parseFloat(selectedTax.dataset.rate) || 0 : 0;
                const taxAmount = afterDiscount * (taxRate / 100);
                
                const grandTotal = afterDiscount + taxAmount;

                displaySubtotal.textContent = subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                displayDiscount.textContent = overallDiscAmt > 0 ? '-' + overallDiscAmt.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00';
                displayTax.textContent = taxAmount.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                displayGrandTotal.textContent = grandTotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'group item-row';
                tr.innerHTML = `
                    <td class="px-2 py-3">
                        <select name="products[]" class="w-full text-sm bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm product-select" style="min-width: 230px;" required>
                            <option value="" data-price="0" data-unit="">{{ $mode === 'biaya' ? '-- Pilih Akun Biaya --' : '-- Pilih Produk / Akun Biaya --' }}</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td class="px-2 py-3">
                        <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="hidden" name="row_docs[]" class="input-row-doc" value=""><input type="text" name="row_descriptions[]" class="w-full text-sm bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" placeholder="Opsional">
                    </td>
                    <td class="px-2 py-3">
                        <input type="number" step="0.01" name="quantities[]" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-qty" value="1" min="0.01" required>
                    </td>
                    <td class="px-2 py-3 text-center">
                        <span class="row-unit text-gray-400 text-xs">pcs</span>
                    </td>
                    <td class="px-2 py-3 relative">
                        <input type="hidden" name="prices[]" class="input-price" value="0">
                        <input type="text" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                    </td>
                    <td class="px-2 py-3">
                        <input type="number" step="0.01" name="discounts[]" class="w-full text-sm text-right bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm input-discount" value="0" min="0" max="100">
                    </td>
                    <td class="px-2 py-3 text-right font-medium text-white row-total">
                        0.00
                    </td>
                    <td class="px-2 py-3 text-center">
                        <button type="button" class="text-gray-500 hover:text-red-400 transition-colors btn-remove-row">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                `;
                tableBody.appendChild(tr);
                attachRowListeners(tr);
                updateRemoveButtons();
            });

            function attachRowListeners(row) {
                const select = row.querySelector('.product-select');
                const priceInput = row.querySelector('.input-price');
                const priceDisplay = row.querySelector('.input-price-display');
                const unitLabel = row.querySelector('.row-unit');
                const discInput = row.querySelector('.input-discount');
                
                select.addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    const price = option.dataset.price || '0';
                    const unit = option.dataset.unit || 'pcs';
                    priceInput.value = price;
                    if(priceDisplay) {
                        priceDisplay.value = price;
                        formatRupiah(priceDisplay, priceInput);
                    }
                    if(unitLabel) {
                        unitLabel.textContent = unit || 'pcs';
                    }
                    calculateTotals();
                });

                row.querySelector('.input-qty').addEventListener('input', calculateTotals);
                if(discInput) discInput.addEventListener('input', calculateTotals);
                
                const btnRemove = row.querySelector('.btn-remove-row');
                btnRemove.addEventListener('click', function() {
                    if (document.querySelectorAll('.item-row').length > 1) {
                        row.remove();
                        calculateTotals();
                        updateRemoveButtons();
                    }
                });
            }

            function updateRemoveButtons() {
                const rows = document.querySelectorAll('.item-row');
                const btns = document.querySelectorAll('.btn-remove-row');
                if (rows.length === 1) {
                    btns[0].disabled = true;
                    btns[0].classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    btns.forEach(btn => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    });
                }
            }

            // Init existing rows
            document.querySelectorAll('.item-row').forEach(row => attachRowListeners(row));
            taxSelect.addEventListener('change', calculateTotals);
            
            // Initial calc
            calculateTotals();
            updateRemoveButtons();
        });
    </script>
    @include('partials.prefill-rows', ['rows' => $prefill['rows']])
</x-app-layout>

