<x-app-layout>
    @section('title', 'Buat Retur Penjualan')
    @section('subtitle', 'Catat pengembalian barang yang membatalkan pendapatan dan mengembalikan stok')

    <div class="mb-4">
        <a href="{{ route('client.sales.returns') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Retur
        </a>
    </div>

    <form action="{{ route('client.sales.returns.store') }}" method="POST" id="returnForm">
        @csrf
        
        <div class="doc-card">
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Informasi Retur</h3>
                    <div class="doc-number">
                        <span class="doc-number-label">No. Retur</span>
                        <input type="text" name="return_number" value="{{ $returnNumber }}" class="doc-number-input" readonly>
                    </div>
                </div>

                <div class="form-grid form-cols-3">
                    <div>
                        <label for="customer_id">Pelanggan <span class="req">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ old('customer_id') == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sales_invoice_id">Faktur Terkait <span class="req">*</span></label>
                        <select name="sales_invoice_id" id="sales_invoice_id" required>
                            <option value="">-- Pilih Faktur --</option>
                            @foreach($invoices as $inv)
                                <option value="{{ $inv->id }}" {{ old('sales_invoice_id') == $inv->id ? 'selected' : '' }}>{{ $inv->invoice_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="return_date">Tanggal Retur <span class="req">*</span></label>
                        <input type="date" name="return_date" id="return_date" value="{{ old('return_date', date('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>

            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Barang yang Dikembalikan</h3>
                </div>

                <div class="line-wrap">
                    <table class="line-items">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="width: 35%;">Pilih Barang</th>
                                <th style="width: 100px;" class="r">Qty Retur</th>
                                <th style="width: 150px;" class="r">Harga Retur</th>
                                <th style="width: 150px;" class="r">Total Retur</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td class="col-item">
                                    <select name="products[]" class="product-select" required>
                                        <option value="" data-price="0">-- Pilih Barang --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" data-price="{{ $prod->sell_price }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="col-qty">
                                    <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required>
                                </td>
                                <td class="col-price">
                                    <input type="hidden" name="prices[]" class="input-price" value="0">
                                    <input type="text" class="input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                                </td>
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
                        <label for="notes">Keterangan Retur (Opsional)</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Alasan retur barang...">{{ old('notes') }}</textarea>
                    </div>
                    @include('partials.doc-totals', ['label' => 'Total Retur', 'returnTax' => true])
                </div>
            </div>

            <div class="doc-actions">
                <div class="doc-actions-info">
                    Retur akan mengurangi nilai Piutang Usaha dan Pendapatan, serta menambah Persediaan Barang.
                </div>
                <span class="doc-actions-total">Total <b data-total-mirror>0,00</b></span>
                <div class="doc-actions-buttons">
                    <a href="{{ route('client.sales.returns') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        Simpan Retur
                    </button>
                </div>
            </div>
        </div>
    </form>

    @php
        // Diskon, tarif, dan status PPN baris faktur, supaya total di layar sama dengan yang dijurnal
        $returnDocs = $invoices->mapWithKeys(fn ($i) => [$i->id => ['disc' => (float) $i->discount_percent, 'rate' => (float) $i->tax_percent,
            'lines' => $i->items->reverse()->mapWithKeys(fn ($l) => [($l->product_id ?: 'a' . ($l->account_id ?? '')) => (bool) $l->is_taxable])]]);
    @endphp
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('itemsBody');
            const btnAddRow = document.getElementById('btnAddRow');
            const displayGrandTotal = document.getElementById('displayGrandTotal');
            const invoiceSelect = document.getElementById('sales_invoice_id');
            const returnDocs = @json($returnDocs);

            const productOptions = `@foreach($products as $prod)<option value="{{ $prod->id }}" data-price="{{ $prod->sell_price }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>@endforeach`;

            window.calculateTotals = function() {
                let subtotal = 0, taxable = 0;
                const doc = returnDocs[invoiceSelect.value] || { disc: 0, rate: 0, lines: {} };
                const fmt = (n) => n.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.input-price').value) || 0;
                    const lineTotal = qty * price;
                    
                    row.querySelector('.row-total').textContent = fmt(lineTotal);
                    subtotal += lineTotal;
                    if (doc.lines[row.querySelector('.product-select').value] !== false) taxable += lineTotal;
                });

                const factor = 1 - doc.disc / 100;
                const tax = doc.rate > 0 ? window.LineTax.amount(taxable * factor, doc.rate) : 0;
                document.getElementById('displaySubtotal').textContent = fmt(subtotal);
                document.getElementById('displayDiscount').textContent = '-' + fmt(subtotal - subtotal * factor);
                document.getElementById('displayTax').textContent = fmt(tax);
                document.getElementById('rowReturnDiscount').hidden = doc.disc <= 0;
                document.getElementById('rowReturnTax').hidden = doc.rate <= 0;
                displayGrandTotal.textContent = fmt(subtotal * factor + tax);
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td class="col-item">
                        <select name="products[]" class="product-select" required>
                            <option value="" data-price="0">-- Pilih Barang --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td class="col-qty">
                        <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required>
                    </td>
                    <td class="col-price">
                        <input type="hidden" name="prices[]" class="input-price" value="0">
                        <input type="text" class="input-price-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                    </td>
                    <td class="col-total row-total">0.00</td>
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
                
                select.addEventListener('change', function() {
                    const option = this.options[this.selectedIndex];
                    const price = option.dataset.price || '0';
                    priceInput.value = price;
                    if(priceDisplay) {
                        priceDisplay.value = price;
                        formatRupiah(priceDisplay, priceInput);
                    }
                    calculateTotals();
                });

                row.querySelector('.input-qty').addEventListener('input', calculateTotals);
                
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

            document.querySelectorAll('.item-row').forEach(row => attachRowListeners(row));
            invoiceSelect.addEventListener('change', calculateTotals);
            calculateTotals();
            updateRemoveButtons();
        });
    </script>
    @include('partials.prefill-rows', ['rows' => []])
</x-app-layout>
