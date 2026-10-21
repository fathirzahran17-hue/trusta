<x-app-layout>
    @section('title', 'Buat Retur Pembelian')
    @section('subtitle', 'Catat pengembalian barang ke pemasok yang memotong hutang dan mengurangi stok')

    <div class="mb-4">
        <a href="{{ route('client.purchases.returns') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Retur
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('client.purchases.returns.store') }}" method="POST" id="returnForm">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 14px;">
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Informasi Retur</h3>
                    <div style="text-align: right;">
                        <span style="font-size: 0.65rem; color: #64748b; display: block;">No. Retur</span>
                        <input type="text" name="return_number" value="{{ $returnNumber }}" style="font-size: 1rem; font-weight: 700; color: var(--trusta-teal); background: transparent; border: none; text-align: right; padding: 0; outline: none; width: 150px;" readonly>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="supplier_id">Pemasok <span style="color:#f87171">*</span></label>
                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">-- Pilih Pemasok --</option>
                            @foreach($suppliers as $supp)
                                <option value="{{ $supp->id }}" {{ old('supplier_id') == $supp->id ? 'selected' : '' }}>{{ $supp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purchase_invoice_id">Faktur Terkait (Opsional)</label>
                        <select name="purchase_invoice_id" id="purchase_invoice_id">
                            <option value="">-- Pilih Faktur --</option>
                            @foreach($invoices as $inv)
                                <option value="{{ $inv->id }}" {{ old('purchase_invoice_id') == $inv->id ? 'selected' : '' }}>{{ $inv->invoice_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="return_date">Tanggal Retur <span style="color:#f87171">*</span></label>
                        <input type="date" name="return_date" id="return_date" value="{{ old('return_date', date('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>

            <div style="padding: 14px 18px; background: rgba(15,23,42,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Barang yang Dikembalikan</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="padding: 6px 8px; width: 35%;">Pilih Barang</th>
                                <th style="padding: 6px 8px; width: 100px; text-align: right;">Qty Retur</th>
                                <th style="padding: 6px 8px; width: 150px; text-align: right;">Harga Retur</th>
                                <th style="padding: 6px 8px; width: 150px; text-align: right;">Total Retur</th>
                                <th style="padding: 6px 8px; width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td style="padding: 4px 4px;">
                                    <select name="products[]" class="product-select" required style="width: 100%;">
                                        <option value="" data-price="0">-- Pilih Barang --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" data-price="{{ $prod->buy_price }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required style="width: 100%; text-align: right;">
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="hidden" name="prices[]" class="input-price" value="0">
                                    <input type="text" class="input-price-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                                </td>
                                <td style="padding: 4px 8px; text-align: right; font-weight: 600; color: #fff;" class="row-total">
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
                                <td style="padding: 12px 8px; text-align: right; font-weight: 700; color: #fbbf24; font-size: 1.1rem;" id="displayGrandTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top: 12px;">
                    <label for="notes">Keterangan Retur (Opsional)</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Alasan pengembalian barang ke pemasok..."></textarea>
                </div>
            </div>

            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Retur akan mengurangi nilai Hutang Usaha dan mengurangi Persediaan Barang di tangan.
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('client.purchases.returns') }}" style="padding: 6px 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #94a3b8; font-size: 0.78rem; font-weight: 500; text-decoration: none;">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        Simpan Retur
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
            const displayGrandTotal = document.getElementById('displayGrandTotal');

            const productOptions = `@foreach($products as $prod)<option value="{{ $prod->id }}" data-price="{{ $prod->buy_price }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>@endforeach`;

            window.calculateTotals = function() {
                let subtotal = 0;
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const qty = parseFloat(row.querySelector('.input-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.input-price').value) || 0;
                    const lineTotal = qty * price;
                    
                    row.querySelector('.row-total').textContent = lineTotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    subtotal += lineTotal;
                });

                displayGrandTotal.textContent = subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td style="padding: 4px 4px;">
                        <select name="products[]" class="product-select" required style="width: 100%;">
                            <option value="" data-price="0">-- Pilih Barang --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="number" step="0.01" name="quantities[]" class="input-qty" value="1" min="0.01" required style="width: 100%; text-align: right;">
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="hidden" name="prices[]" class="input-price" value="0">
                        <input type="text" class="input-price-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
                    </td>
                    <td style="padding: 4px 8px; text-align: right; font-weight: 600; color: #fff;" class="row-total">0.00</td>
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
            updateRemoveButtons();
        });
    </script>
</x-app-layout>
