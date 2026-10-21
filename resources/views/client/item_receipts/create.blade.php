<x-app-layout>
    @section('title', 'Buat Penerimaan Barang')
    @section('subtitle', 'Mencatat penerimaan barang dan menambah stok persediaan')

    <div class="mb-4">
        <a href="{{ route('client.purchases.receipts') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Penerimaan
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('client.purchases.receipts.store') }}" method="POST" id="receiptForm">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 14px;">
            {{-- Header: Info --}}
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Informasi Penerimaan</h3>
                    <div style="text-align: right;">
                        <span style="font-size: 0.65rem; color: #64748b; display: block;">Nomor Bukti Terima (Auto / Manual)</span>
                        <input type="text" name="receipt_number" value="{{ $receiptNumber }}" style="font-size: 1rem; font-weight: 700; color: var(--trusta-teal); background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; text-align: right; padding: 2px 6px; outline: none; width: 170px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="supplier_id">Pemasok <span style="color:#f87171">*</span></label>
                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">-- Pilih Pemasok --</option>
                            @foreach($suppliers as $supp)
                                <option value="{{ $supp->id }}" {{ old('supplier_id', optional($prefill['order'])->supplier_id) == $supp->id ? 'selected' : '' }}>{{ $supp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="purchase_order_id">Dari Pesanan (PO)</label>
                        <select name="purchase_order_id" id="purchase_order_id">
                            <option value="">-- Pilih PO (Opsional) --</option>
                            @foreach($purchaseOrders as $po)
                                <option value="{{ $po->id }}" {{ old('purchase_order_id', optional($prefill['order'])->id) == $po->id ? 'selected' : '' }}>{{ $po->po_number }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="warehouse_id">Gudang Tujuan <span style="color:#f87171">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="receipt_date">Tanggal Terima <span style="color:#f87171">*</span></label>
                        <input type="date" name="receipt_date" id="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
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
            <div style="padding: 14px 18px; background: rgba(15,23,42,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Barang yang Diterima</h3>
                    <div style="font-size: 0.75rem; color: #cbd5e1;">
                        Status: 
                        <select name="status" style="padding: 2px 8px; border-radius: 4px; background: #1e293b; border: 1px solid #334155; color: #fff; font-size: 0.75rem;">
                            <option value="draft">Draft</option>
                            <option value="received" selected>Diterima (Tambah Stok & Hutang)</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="padding: 6px 8px; width: 40%;">Pilih Barang</th>
                                <th style="padding: 6px 8px; width: 40%;">Keterangan</th>
                                <th style="padding: 6px 8px; width: 100px; text-align: right;">Qty Terima</th>
                                <th style="padding: 6px 8px; width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td style="padding: 4px 4px;">
                                    <select name="products[]" required style="width: 100%;">
                                        <option value="">-- Pilih Barang --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="text" name="row_descriptions[]" placeholder="Opsional" style="width: 100%;">
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="number" step="0.01" name="quantities[]" value="1" min="0.01" required style="width: 100%; text-align: right;">
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
                                <td colspan="4" style="padding: 8px;">
                                    <button type="button" id="btnAddRow" style="color: var(--trusta-teal); background: none; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                        <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Baris
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Jika status diset ke 'Diterima', sistem akan menambah stok barang dan menjurnal Hutang Usaha / Persediaan.
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('client.purchases.receipts') }}" style="padding: 6px 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #94a3b8; font-size: 0.78rem; font-weight: 500; text-decoration: none;">
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

            const productOptions = `@foreach($products as $prod)<option value="{{ $prod->id }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }}</option>@endforeach`;

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td style="padding: 4px 4px;">
                        <select name="products[]" required style="width: 100%;">
                            <option value="">-- Pilih Barang --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="text" name="row_descriptions[]" placeholder="Opsional" style="width: 100%;">
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="number" step="0.01" name="quantities[]" value="1" min="0.01" required style="width: 100%; text-align: right;">
                    </td>
                    <td style="padding: 4px 4px; text-align: center;">
                        <button type="button" class="btn-remove-row" style="color: #64748b; background: none; border: none; cursor: pointer; padding: 2px;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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
