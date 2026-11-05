<x-app-layout>
    @section('title', 'Buat Pengiriman Pesanan (DO)')
    @section('subtitle', 'Mencatat pengiriman barang dan memotong stok persediaan')

    <div class="mb-4">
        <a href="{{ route('client.sales.deliveries') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pengiriman
        </a>
    </div>

    @include('partials.pull-multi', ['label' => 'Tarik dari Pesanan Penjualan (SO)', 'partyWord' => 'pelanggan', 'docWord' => 'pengiriman', 'options' => $options, 'checked' => $checked, 'baseUrl' => route('client.sales.deliveries.create'), 'sourceText' => $prefill['source'], 'notice' => $prefill['ignored'] ? $prefill['ignored'] . ' pesanan dilewati karena beda pelanggan.' : (($prefill['source'] && empty($prefill['rows'])) ? 'Semua barang pada pesanan yang dipilih sudah dikirim.' : null)])

    <form action="{{ route('client.sales.deliveries.store') }}" method="POST" id="deliveryForm">
        @csrf
        
        <div class="doc-card">
            {{-- Header: Info --}}
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Informasi Pengiriman</h3>
                    <div class="doc-number">
                        <span class="doc-number-label">Nomor DO (Auto / Manual)</span>
                        <input type="text" name="delivery_number" value="{{ $deliveryNumber }}" class="doc-number-input">
                    </div>
                </div>

                <div class="form-grid form-cols-3">
                    <div>
                        <label for="customer_id">Pelanggan <span class="req">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ old('customer_id', $prefill['party_id']) == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label>Dari Pesanan</label>
                        <div class="doc-static">{{ $prefill['source'] ?: 'Tanpa pesanan (bebas)' }}</div>
                    </div>

                    <div>
                        <label for="delivery_date">Tanggal Kirim <span class="req">*</span></label>
                        <input type="date" name="delivery_date" id="delivery_date" value="{{ old('delivery_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="form-grid form-cols-3">
                    <div>
                        <label for="driver_name">Nama Pengirim / Supir</label>
                        <input type="text" name="driver_name" id="driver_name" value="{{ old('driver_name') }}" placeholder="Contoh: Budi">
                    </div>
                    <div>
                        <label for="license_plate">Plat Nomor / Resi</label>
                        <input type="text" name="license_plate" id="license_plate" value="{{ old('license_plate') }}" placeholder="Contoh: B 1234 CD">
                    </div>
                    <div>
                        <label for="shipping_address">Alamat Pengiriman</label>
                        <input type="text" name="shipping_address" id="shipping_address" value="{{ old('shipping_address') }}" placeholder="Alamat lengkap penerima">
                    </div>
                </div>
            </div>

            {{-- Item Table --}}
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Barang yang Dikirim</h3>
                    <div class="doc-inline-field">
                        Status: 
                        <select name="status">
                            <option value="draft">Draft</option>
                            <option value="shipped" selected>Dikirim (Potong Stok)</option>
                        </select>
                    </div>
                </div>

                <div class="line-wrap">
                    <table class="line-items">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="width: 40%;">Pilih Barang</th>
                                <th style="width: 40%;">Keterangan</th>
                                <th style="width: 100px;" class="r">Qty Kirim</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td class="col-item">
                                    <select name="products[]" required>
                                        <option value="">-- Pilih Barang --</option>
                                        @include('partials.product-options', ['products' => $products, 'context' => 'sales'])
                                    </select>
                                </td>
                                <td class="col-desc">
                                    <input type="hidden" name="row_orders[]" class="input-row-order" value=""><input type="text" name="row_descriptions[]" placeholder="Opsional">
                                </td>
                                <td class="col-qty">
                                    <input type="number" step="0.01" name="quantities[]" value="1" min="0.01" required>
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
                    Status 'Dikirim' langsung memotong stok. Jurnal pendapatan dan HPP dibuat saat Faktur Penjualan dicatat.
                </div>
                <div class="doc-actions-buttons">
                    <a href="{{ route('client.sales.deliveries') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        Simpan Pengiriman
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('itemsBody');
            const btnAddRow = document.getElementById('btnAddRow');

            const productOptions = `@include('partials.product-options', ['products' => $products, 'context' => 'sales'])`;

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
    @include('partials.material-sale-warning')
</x-app-layout>
