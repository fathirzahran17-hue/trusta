<x-app-layout>
    @section('title', 'Buat Faktur Penjualan Baru')
    @section('subtitle', 'Pencatatan faktur otomatis terbentuk Jurnal Akuntansi')

    <div class="mb-4">
        <a href="{{ route('client.sales-invoices.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Faktur
        </a>
    </div>

    @include('partials.pull-multi', ['label' => 'Tarik dari Pengiriman (DO) / Pesanan Penjualan (SO)', 'partyWord' => 'pelanggan', 'options' => array_merge($deliveries, $sourceOrders), 'checked' => $checked, 'baseUrl' => route('client.sales-invoices.create'), 'sourceText' => $prefill['source'], 'notice' => $prefill['ignored'] ? $prefill['ignored'] . ' dokumen dilewati karena beda pelanggan.' : (($prefill['source'] && empty($prefill['rows'])) ? 'Semua barang pada dokumen yang dipilih sudah ditagih.' : null)])

    <form action="{{ route('client.sales-invoices.store') }}" method="POST" id="invoiceForm">
        @csrf
        
        <div class="doc-card">
            {{-- Header: Customer & Doc Info --}}
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Informasi Pelanggan & Dokumen</h3>
                    <div class="doc-number">
                        <span class="doc-number-label">Nomor Faktur</span>
                        <span class="doc-number-value">{{ $invoiceNumber }}</span>
                    </div>
                </div>

                <div class="form-grid form-cols-wide">
                    <div>
                        <label for="customer_id">Pelanggan (Customer) <span class="req">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" data-term="{{ (int) $cust->default_term_days }}" {{ old('customer_id', $prefill['party_id']) == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="invoice_date">Tanggal Faktur <span class="req">*</span></label>
                        <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                    </div>

                    <div>
                        <label for="due_date">Jatuh Tempo <span class="req">*</span></label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+' . $termDays . ' days'))) }}" required>
                    </div>
                </div>
            </div>

            {{-- Item Table --}}
            <div class="doc-section">
                <h3 class="doc-section-title">Item Produk / Layanan</h3>

                @include('partials.invoice-layout', [
                    'layouts' => $layouts, 'layout' => $layout, 'warnShortage' => true,
                    'itemHeads' => array_intersect_key(['barang' => 'Barang', 'jasa' => 'Jasa'], $layouts),
                    'hints' => array_intersect_key([
                        'barang' => 'Pilih barang yang dijual. Pendapatan, harga pokok, dan persediaan dijurnal otomatis mengikuti data barang.',
                        'jasa' => 'Jasa tanpa stok. Pendapatan dijurnal otomatis mengikuti data jasa.',
                    ], $layouts),
                ])
                <template id="opts-barang">@include('partials.product-options', ['products' => $products->where('type', 'goods'), 'context' => 'sales', 'priceField' => 'sell_price', 'stockMap' => $stockMap])</template>
                <template id="opts-jasa">@include('partials.product-options', ['products' => $products->where('type', '!=', 'goods'), 'context' => 'sales', 'priceField' => 'sell_price'])</template>

                <div class="line-wrap">
                    @include("partials.no-products")
                    <table class="line-items" id="itemsTable">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="col-item" style="width: 30%;">Barang</th>
                                <th class="col-desc" style="width: 16%;">Keterangan</th>
                                <th class="col-stock r" style="width: 76px;">Stok</th>
                                <th style="width: 70px;" class="r">Qty</th>
                                <th style="width: 50px;" class="c">Satuan</th>
                                <th style="width: 150px; min-width: 150px;" class="r">Harga Satuan</th>
                                <th style="width: 84px; min-width: 84px;" class="r">Disk (%)</th>
                                <th class="col-tax c" style="width: 52px;" title="Centang bila baris ini kena PPN">PPN</th>
                                <th style="width: 120px;" class="r">Jumlah</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="group item-row">
                                <td class="col-item">
                                    <select name="products[]" class="product-select" required></select>
                                </td>
                                <td class="col-desc">
                                    <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="hidden" name="row_docs[]" class="input-row-doc" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional">
                                </td>
                                <td class="col-stock"><span class="row-stock">-</span></td>
                                <td class="col-qty">
                                    <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required>
                                </td>
                                <td class="col-unit">
                                    <span class="row-unit">pcs</span>
                                </td>
                                <td class="col-price">
                                    <input type="hidden" name="prices[]" class="input-price" value="0">
                                    <input type="text" class="input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                                </td>
                                <td class="col-disc">
                                    <input type="number" step="0.01" name="discounts[]" class="input-discount" value="0" min="0" max="100">
                                </td>
                                @include('partials.line-tax-cell')
                                <td class="col-total row-total">
                                    0.00
                                </td>
                                <td class="col-act">
                                    <button type="button" class="btn-remove-row" disabled>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        </table>
                </div>
                <button type="button" id="btnAddRow" class="line-add">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Baris
                                    </button>

                <div class="doc-summary">
                    <div>
                        <label for="notes">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Catatan untuk pelanggan (tertera di invoice)">{{ old('notes') }}</textarea>
                        @include('partials.tax-number-field', ['hint' => 'Nomor dari Coretax. Bisa diisi nanti dari halaman detail faktur.'])
                    </div>
                    @include('partials.doc-totals', ['taxes' => $taxes, 'editTaxId' => $prefill['tax_id'] ?? null, 'taxFromSource' => !empty($prefill['rows'])])
                </div>
            </div>

            {{-- Footer --}}
            <div class="doc-actions">
                <div class="doc-actions-info">
                    Menyimpan invoice akan secara otomatis membuat Jurnal Akuntansi (Piutang & Pendapatan).
                </div>
                <span class="doc-actions-total">Total <b data-total-mirror>0,00</b></span>
                <div class="doc-actions-buttons">
                    <a href="{{ route('client.sales-invoices.index') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" id="btnSubmit" class="btn-primary">
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


            window.calculateTotals = function() {
                let subtotal = 0, taxableSubtotal = 0;
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.input-price').value) || 0;
                    const discPct = parseFloat(row.querySelector('.input-discount').value) || 0;
                    const lineTotal = qty * price;
                    const discountAmt = lineTotal * (discPct / 100);
                    const total = lineTotal - discountAmt;
                    
                    row.querySelector('.row-total').textContent = window.fmtAmount(total);
                    subtotal += total;
                    if (window.LineTax.isTaxable(row)) taxableSubtotal += total;
                });

                // Overall discount
                const overallDiscPct = parseFloat(discountInput.value) || 0;
                const overallDiscAmt = subtotal * (overallDiscPct / 100);
                const afterDiscount = subtotal - overallDiscAmt;

                // Tax
                const selectedTax = taxSelect.options[taxSelect.selectedIndex];
                const taxRate = selectedTax ? parseFloat(selectedTax.dataset.rate) || 0 : 0;
                const taxAmount = window.LineTax.apply(taxableSubtotal * (1 - overallDiscPct / 100), taxRate, afterDiscount);
                
                const grandTotal = afterDiscount + taxAmount;

                displaySubtotal.textContent = window.fmtAmount(subtotal);
                displayDiscount.textContent = overallDiscAmt > 0 ? '-' + window.fmtAmount(overallDiscAmt) : '0,00';
                displayTax.textContent = window.fmtAmount(taxAmount);
                displayGrandTotal.textContent = window.fmtAmount(grandTotal);
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'group item-row';
                tr.innerHTML = `
                    <td class="col-item">
                        <select name="products[]" class="product-select" required>${window.InvoiceLayout.options()}</select>
                    </td>
                    <td class="col-desc">
                        <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="hidden" name="row_docs[]" class="input-row-doc" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional">
                    </td>
                    <td class="col-stock"><span class="row-stock">-</span></td>
                                <td class="col-qty">
                        <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required>
                    </td>
                    <td class="col-unit">
                        <span class="row-unit">pcs</span>
                    </td>
                    <td class="col-price">
                        <input type="hidden" name="prices[]" class="input-price" value="0">
                        <input type="text" class="input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                    </td>
                    <td class="col-disc">
                        <input type="number" step="0.01" name="discounts[]" class="input-discount" value="0" min="0" max="100">
                    </td>
                    @include('partials.line-tax-cell')
                    <td class="col-total row-total">
                        0.00
                    </td>
                    <td class="col-act">
                        <button type="button" class="btn-remove-row">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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
                const discountInput = row.querySelector('.input-discount');
                
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
                if(discountInput) discountInput.addEventListener('input', calculateTotals);
                
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
                    btns[0].style.opacity = '0.3';
                    btns[0].style.cursor = 'not-allowed';
                } else {
                    btns.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    });
                }
            }

            // Init existing rows
            window.InvoiceLayout.init();
            document.querySelectorAll('.item-row').forEach(row => attachRowListeners(row));
            taxSelect.addEventListener('change', calculateTotals);
            
            // Initial calc
            calculateTotals();
            updateRemoveButtons();
        });
    </script>
    @include('partials.prefill-rows', ['rows' => $prefill['rows']])
    @include('partials.due-date-sync', ['partySelect' => '#customer_id', 'defaultDays' => $termDays])
    @include('partials.material-sale-warning')
</x-app-layout>
