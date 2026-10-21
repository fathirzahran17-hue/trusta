<x-app-layout>
    @section('title', 'Buat Faktur Penjualan Baru')
    @section('subtitle', 'Pencatatan faktur otomatis terbentuk Jurnal Akuntansi')

    <div class="mb-4">
        <a href="{{ route('client.sales-invoices.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Faktur
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    @include('partials.pull-multi', ['label' => 'Tarik dari Pengiriman (DO) / Pesanan Penjualan (SO)', 'partyWord' => 'pelanggan', 'options' => array_merge($deliveries, $sourceOrders), 'checked' => $checked, 'baseUrl' => route('client.sales-invoices.create'), 'sourceText' => $prefill['source'], 'notice' => $prefill['ignored'] ? $prefill['ignored'] . ' dokumen dilewati karena beda pelanggan.' : (($prefill['source'] && empty($prefill['rows'])) ? 'Semua barang pada dokumen yang dipilih sudah ditagih.' : null)])

    <form action="{{ route('client.sales-invoices.store') }}" method="POST" id="invoiceForm">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: visible; margin-bottom: 14px;">
            {{-- Header: Customer & Doc Info --}}
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Informasi Pelanggan & Dokumen</h3>
                    <div style="text-align: right;">
                        <span style="font-size: 0.65rem; color: #64748b; display: block;">Nomor Faktur</span>
                        <span style="font-size: 1rem; font-weight: 700; color: var(--trusta-teal);">{{ $invoiceNumber }}</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 12px;">
                    <div>
                        <label for="customer_id">Pelanggan (Customer) <span style="color:#f87171">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ old('customer_id', $prefill['party_id']) == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="invoice_date">Tanggal Faktur <span style="color:#f87171">*</span></label>
                        <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                    </div>

                    <div>
                        <label for="due_date">Jatuh Tempo <span style="color:#f87171">*</span></label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" required>
                    </div>
                </div>
            </div>

            {{-- Item Table --}}
            <div style="padding: 14px 18px; background: rgba(15,23,42,0.3);">
                <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin-bottom: 10px;">Item Produk / Layanan</h3>

                <div style="overflow: visible;">
                    @include("partials.no-products")
                    <table class="w-full text-sm text-left" id="itemsTable">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="padding: 6px 8px; width: 28%;">Produk / Jasa</th>
                                <th style="padding: 6px 8px; width: 18%;">Keterangan</th>
                                <th style="padding: 6px 8px; width: 70px; text-align: right;">Qty</th>
                                <th style="padding: 6px 8px; width: 50px; text-align: center;">Satuan</th>
                                <th style="padding: 6px 8px; width: 120px; text-align: right;">Harga Satuan</th>
                                <th style="padding: 6px 8px; width: 70px; text-align: right;">Disk (%)</th>
                                <th style="padding: 6px 8px; width: 120px; text-align: right;">Jumlah</th>
                                <th style="padding: 6px 8px; width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="group item-row">
                                <td style="padding: 4px 4px;">
                                    <select name="products[]" class="product-select" required style="width: 100%;">
                                        <option value="" data-price="0" data-unit="">-- Pilih Produk --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" data-price="{{ $prod->sell_price }}" data-unit="{{ $prod->unit }}" data-type="{{ $prod->type }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional" style="width: 100%;">
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required style="width: 100%; text-align: right;">
                                </td>
                                <td style="padding: 4px 4px; text-align: center;">
                                    <span class="row-unit" style="color: #94a3b8; font-size: 0.75rem;">pcs</span>
                                </td>
                                <td style="padding: 4px 4px; position: relative;">
                                    <input type="hidden" name="prices[]" class="input-price" value="0">
                                    <input type="text" class="input-price-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="number" step="0.01" name="discounts[]" class="input-discount" value="0" min="0" max="100" style="width: 100%; text-align: right;">
                                </td>
                                <td style="padding: 4px 8px; text-align: right; font-weight: 600; color: #fff; font-size: 0.8rem;" class="row-total">
                                    0.00
                                </td>
                                <td style="padding: 4px 4px; text-align: center;">
                                    <button type="button" class="btn-remove-row" disabled style="color: #475569; background: none; border: none; cursor: pointer; padding: 2px; opacity: 0.5;">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-gray-700 bg-gray-800/30">
                            <tr>
                                <td colspan="3" style="padding: 8px;">
                                    <button type="button" id="btnAddRow" style="color: var(--trusta-teal); background: none; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                        <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Baris
                                    </button>
                                </td>
                                <td colspan="3" style="padding: 8px; text-align: right; color: #94a3b8; font-weight: 500; font-size: 0.78rem;">Subtotal</td>
                                <td style="padding: 8px; text-align: right; font-weight: 700; color: #fff; font-size: 0.9rem;" id="displaySubtotal">0.00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="3" style="padding: 8px; text-align: right;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                                        <span style="color: #94a3b8; font-weight: 500; font-size: 0.78rem;">Diskon Keseluruhan (%)</span>
                                        <input type="number" step="0.01" name="discount_percent" id="discount_percent" value="0" min="0" max="100" style="width: 70px; text-align: right;" onchange="calculateTotals()" onkeyup="calculateTotals()">
                                    </div>
                                </td>
                                <td style="padding: 8px; text-align: right; font-weight: 700; color: #fbbf24; font-size: 0.9rem;" id="displayDiscount">0.00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="3" style="padding: 8px; text-align: right;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                                        <span style="color: #94a3b8; font-weight: 500; font-size: 0.78rem;">Pajak</span>
                                        <select name="tax_id" id="tax_id" style="width: 130px;">
                                            <option value="" data-rate="0">Tanpa Pajak</option>
                                            @foreach($taxes as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}">{{ $tax->name }} ({{ (float)$tax->rate }}%)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td style="padding: 8px; text-align: right; font-weight: 700; color: #f87171; font-size: 0.9rem;" id="displayTax">0.00</td>
                                <td></td>
                            </tr>
                            <tr style="background: rgba(30,41,59,0.6);">
                                <td colspan="3"></td>
                                <td colspan="3" style="padding: 12px 8px; text-align: right; color: #fff; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Grand Total</td>
                                <td style="padding: 12px 8px; text-align: right; font-weight: 700; color: var(--trusta-teal); font-size: 1.1rem;" id="displayGrandTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top: 12px;">
                    <label for="notes">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Catatan untuk pelanggan (tertera di invoice)"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Menyimpan invoice akan secara otomatis membuat Jurnal Akuntansi (Piutang & Pendapatan).
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('client.sales-invoices.index') }}" style="padding: 6px 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #94a3b8; font-size: 0.78rem; font-weight: 500; text-decoration: none;">
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
        function formatRupiah(element, hiddenElement) {
            let value = element.value.toString().replace(/[^,\d]/g, '');
            let split = value.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            element.value = rupiah;
            
            let realValue = value ? value.replace(/,/g, '.') : 0;
            if(hiddenElement) {
                hiddenElement.value = realValue;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('itemsBody');
            const btnAddRow = document.getElementById('btnAddRow');
            const taxSelect = document.getElementById('tax_id');
            const discountInput = document.getElementById('discount_percent');
            const displaySubtotal = document.getElementById('displaySubtotal');
            const displayDiscount = document.getElementById('displayDiscount');
            const displayTax = document.getElementById('displayTax');
            const displayGrandTotal = document.getElementById('displayGrandTotal');

            const productOptions = `@foreach($products as $prod)<option value="{{ $prod->id }}" data-price="{{ $prod->sell_price }}" data-unit="{{ $prod->unit }}" data-type="{{ $prod->type }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>@endforeach`;

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

                // Overall discount
                const overallDiscPct = parseFloat(discountInput.value) || 0;
                const overallDiscAmt = subtotal * (overallDiscPct / 100);
                const afterDiscount = subtotal - overallDiscAmt;

                // Tax
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
                    <td style="padding: 4px 4px;">
                        <select name="products[]" class="product-select" required style="width: 100%;">
                            <option value="" data-price="0" data-unit="">-- Pilih Produk --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional" style="width: 100%;">
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required style="width: 100%; text-align: right;">
                    </td>
                    <td style="padding: 4px 4px; text-align: center;">
                        <span class="row-unit" style="color: #94a3b8; font-size: 0.75rem;">pcs</span>
                    </td>
                    <td style="padding: 4px 4px; position: relative;">
                        <input type="hidden" name="prices[]" class="input-price" value="0">
                        <input type="text" class="input-price-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="number" step="0.01" name="discounts[]" class="input-discount" value="0" min="0" max="100" style="width: 100%; text-align: right;">
                    </td>
                    <td style="padding: 4px 8px; text-align: right; font-weight: 600; color: #fff; font-size: 0.8rem;" class="row-total">
                        0.00
                    </td>
                    <td style="padding: 4px 4px; text-align: center;">
                        <button type="button" class="btn-remove-row" style="color: #64748b; background: none; border: none; cursor: pointer; padding: 2px;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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
            document.querySelectorAll('.item-row').forEach(row => attachRowListeners(row));
            taxSelect.addEventListener('change', calculateTotals);
            
            // Initial calc
            calculateTotals();
            updateRemoveButtons();
        });
    </script>
    @include('partials.prefill-rows', ['rows' => $prefill['rows']])
</x-app-layout>

