<x-app-layout>
    @section('title', 'Buat Pengiriman Pesanan (DO)')
    @section('subtitle', 'Mencatat pengiriman barang dan memotong stok persediaan')

    <div class="mb-4">
        <a href="{{ route('client.sales.deliveries') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pengiriman
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('client.sales.deliveries.store') }}" method="POST" id="deliveryForm">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 14px;">
            {{-- Header: Info --}}
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Informasi Pengiriman</h3>
                    <div style="text-align: right;">
                        <span style="font-size: 0.65rem; color: #64748b; display: block;">Nomor DO (Auto / Manual)</span>
                        <input type="text" name="delivery_number" value="{{ $deliveryNumber }}" style="font-size: 1rem; font-weight: 700; color: var(--trusta-teal); background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; text-align: right; padding: 2px 6px; outline: none; width: 170px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="customer_id">Pelanggan <span style="color:#f87171">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ old('customer_id', optional($prefill['order'])->customer_id) == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="sales_order_id">Dari Pesanan (Sales Order)</label>
                        <select name="sales_order_id" id="sales_order_id" onchange="window.location.href = this.value ? '{{ route('client.sales.deliveries.create') }}?sales_order_id=' + this.value : '{{ route('client.sales.deliveries.create') }}'">
                            <option value="">-- Pilih SO (Opsional) --</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}" {{ old('sales_order_id', optional($prefill['order'])->id) == $so->id ? 'selected' : '' }}>{{ $so->order_number }} — {{ optional($so->customer)->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="delivery_date">Tanggal Kirim <span style="color:#f87171">*</span></label>
                        <input type="date" name="delivery_date" id="delivery_date" value="{{ old('delivery_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 12px;">
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
            <div style="padding: 14px 18px; background: rgba(15,23,42,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Barang yang Dikirim</h3>
                    <div style="font-size: 0.75rem; color: #cbd5e1;">
                        Status: 
                        <select name="status" style="padding: 2px 8px; border-radius: 4px; background: #1e293b; border: 1px solid #334155; color: #fff; font-size: 0.75rem;">
                            <option value="draft">Draft</option>
                            <option value="shipped" selected>Dikirim (Potong Stok)</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="padding: 6px 8px; width: 40%;">Pilih Barang</th>
                                <th style="padding: 6px 8px; width: 40%;">Keterangan</th>
                                <th style="padding: 6px 8px; width: 100px; text-align: right;">Qty Kirim</th>
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
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Jika status diset ke 'Dikirim', sistem akan memotong stok barang dan menjurnal HPP.
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('client.sales.deliveries') }}" style="padding: 6px 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #94a3b8; font-size: 0.78rem; font-weight: 500; text-decoration: none;">
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
