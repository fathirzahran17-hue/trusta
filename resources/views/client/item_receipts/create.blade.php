<x-app-layout>
    @section('title', 'Buat Penerimaan Barang')
    @section('subtitle', 'Mencatat penerimaan barang dan menambah stok persediaan')

    <div class="mb-4">
        <a href="{{ route('client.purchases.receipts') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Penerimaan
        </a>
    </div>

    @include('partials.pull-multi', ['label' => 'Tarik dari Pesanan Pembelian (PO)', 'partyWord' => 'pemasok', 'docWord' => 'penerimaan', 'options' => $options, 'checked' => $checked, 'baseUrl' => route('client.purchases.receipts.create'), 'sourceText' => $prefill['source'], 'notice' => $prefill['ignored'] ? $prefill['ignored'] . ' pesanan dilewati karena beda pemasok.' : (($prefill['source'] && empty($prefill['rows'])) ? 'Semua barang pada pesanan yang dipilih sudah diterima.' : null)])

    <form action="{{ route('client.purchases.receipts.store') }}" method="POST" id="receiptForm">
        @csrf
        
        <div class="doc-card">
            {{-- Header: Info --}}
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Informasi Penerimaan</h3>
                    <div class="doc-number">
                        <span class="doc-number-label">Nomor Bukti Terima (Auto / Manual)</span>
                        <input type="text" name="receipt_number" value="{{ $receiptNumber }}" class="doc-number-input">
                    </div>
                </div>

                <div class="form-grid form-cols-4">
                    <div>
                        <label for="supplier_id">Pemasok <span class="req">*</span></label>
                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">-- Pilih Pemasok --</option>
                            @foreach($suppliers as $supp)
                                <option value="{{ $supp->id }}" {{ old('supplier_id', $prefill['party_id']) == $supp->id ? 'selected' : '' }}>{{ $supp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label>Dari Pesanan</label>
                        <div class="doc-static">{{ $prefill['source'] ?: 'Tanpa pesanan (bebas)' }}</div>
                    </div>

                    <div>
                        <label for="warehouse_id">Gudang Tujuan <span class="req">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="receipt_date">Tanggal Terima <span class="req">*</span></label>
                        <input type="date" name="receipt_date" id="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="form-grid form-cols-1-2">
                    <div>
                        <label for="delivery_note_number">No. Surat Jalan Pemasok</label>
                        <input type="text" name="delivery_note_number" id="delivery_note_number" value="{{ old('delivery_note_number') }}" placeholder="Contoh: SJ-1234">
                    </div>
                    <div>
                        <label for="notes">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="notes" id="notes" value="{{ old('notes') }}" placeholder="Catatan internal untuk penerimaan ini...">
                    </div>
                </div>
            </div>

            {{-- Item Table --}}
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Barang yang Diterima</h3>
                    <div class="doc-inline-field">
                        Status: 
                        <select name="status">
                            <option value="draft">Draft</option>
                            <option value="received" selected>Diterima (Tambah Stok)</option>
                        </select>
                    </div>
                </div>

                <div class="line-wrap">
                    <table class="line-items">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="width: 34%;">Pilih Barang</th>
                                <th style="width: 30%;">Keterangan</th>
                                <th style="width: 100px;" class="r">Qty Terima</th>
                                <th style="width: 140px;" class="r">Harga Beli (Rp)</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td class="col-item">
                                    <select name="products[]" required>
                                        <option value="">-- Pilih Barang --</option>
                                        @include('partials.product-options', ['products' => $products, 'context' => 'purchase'])
                                    </select>
                                </td>
                                <td class="col-desc">
                                    <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional">
                                </td>
                                <td class="col-qty">
                                    <input type="number" step="0.01" name="quantities[]" value="1" min="0.01" required>
                                </td>
                                <td class="col-price">
                                    <input type="number" step="0.01" min="0" name="prices[]" class="input-price" placeholder="0">
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
            </div>

            {{-- Footer --}}
            <div class="doc-actions">
                <div class="doc-actions-info">
                    Status 'Diterima' menambah stok dengan harga di atas. Barang dari Pesanan Pembelian memakai harga pesanan. Jurnal persediaan dan hutang dibuat saat Faktur Pembelian dicatat.
                </div>
                <div class="doc-actions-buttons">
                    <a href="{{ route('client.purchases.receipts') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        Simpan Penerimaan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('itemsBody');
            const btnAddRow = document.getElementById('btnAddRow');

            // Harga beli terisi otomatis dari master barang; baris dari pesanan memakai harga pesanan (tidak bisa diubah di sini).
            const buyPrices = @js($products->mapWithKeys(fn ($p) => [$p->id => (float) $p->buy_price]));
            tableBody.addEventListener('change', function (e) {
                if (!e.target.matches('select[name="products[]"]')) return;
                const price = e.target.closest('tr').querySelector('.input-price');
                if (price && !price.readOnly) price.value = buyPrices[e.target.value] || '';
            });
            setTimeout(function () {
                document.querySelectorAll('.item-row').forEach(function (tr) {
                    const order = tr.querySelector('.input-row-order'), price = tr.querySelector('.input-price');
                    if (order && order.value && price) { price.readOnly = true; price.title = 'Mengikuti harga pada Pesanan Pembelian'; }
                });
            }, 0);

            const productOptions = `@include('partials.product-options', ['products' => $products, 'context' => 'purchase'])`;

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td class="col-item">
                        <select name="products[]" required>
                            <option value="">-- Pilih Barang --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td class="col-desc">
                        <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional">
                    </td>
                    <td class="col-qty">
                        <input type="number" step="0.01" name="quantities[]" value="1" min="0.01" required>
                    </td>
                    <td class="col-price">
                        <input type="number" step="0.01" min="0" name="prices[]" class="input-price" placeholder="0">
                    </td>
                    <td class="col-act">
                        <button type="button" class="btn-remove-row">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                `;
                tableBody.appendChild(tr);
                attachRemoveListener(tr.querySelector('.btn-remove-row'));
                updateRemoveButtons();
            });

            function attachRemoveListener(btn) {
                btn.addEventListener('click', function() {
                    if (document.querySelectorAll('.item-row').length > 1) {
                        this.closest('tr').remove();
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

            document.querySelectorAll('.btn-remove-row').forEach(btn => attachRemoveListener(btn));
            updateRemoveButtons();
        });
    </script>
    @include('partials.prefill-rows', ['rows' => $prefill['rows']])
</x-app-layout>
