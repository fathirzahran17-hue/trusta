<x-app-layout>
    @section('title', 'Penerimaan Kas & Bank')
    @section('subtitle', 'Catat dana masuk yang bukan dari faktur penjualan')

    <div class="mb-4">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Kas & Bank
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {!! session('error') !!}
        </div>
    @endif

    <form action="{{ route('client.cash-bank.receipts.store') }}" method="POST">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: visible; margin-bottom: 14px;">
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Informasi Penerimaan</h3>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="receive_to">Terima Ke Akun (Kas/Bank) <span style="color:#f87171">*</span></label>
                        <select name="receive_to" id="receive_to" required>
                            <option value="">-- Pilih Akun --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('receive_to') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="transaction_date">Tanggal <span style="color:#f87171">*</span></label>
                        <input type="date" name="transaction_date" id="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label for="ref_number">No. Referensi (Otomatis jika kosong)</label>
                        <input type="text" name="ref_number" id="ref_number" value="{{ old('ref_number') }}">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
                    <div>
                        <label for="payer">Dari (Pengirim)</label>
                        <input type="text" name="payer" id="payer" value="{{ old('payer') }}" placeholder="Nama pengirim dana...">
                    </div>
                    <div>
                        <label for="description">Keterangan Utama <span style="color:#f87171">*</span></label>
                        <input type="text" name="description" id="description" value="{{ old('description') }}" required placeholder="Keterangan umum transaksi ini...">
                    </div>
                </div>
            </div>

            <div style="padding: 14px 18px; background: rgba(15,23,42,0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Rincian Akun (Dari mana dana berasal?)</h3>
                </div>

                <div style="overflow: visible;">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="padding: 6px 8px; width: 30%;">Pilih Akun (Lawan)</th>
                                <th style="padding: 6px 8px; width: 20%;">Kartu Job</th>
                                <th style="padding: 6px 8px; width: 30%;">Keterangan Baris</th>
                                <th style="padding: 6px 8px; width: 150px; text-align: right;">Jumlah (Rp)</th>
                                <th style="padding: 6px 8px; width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td style="padding: 4px 4px;">
                                    <select name="income_accounts[]" required class="searchable-select" style="width: 100%;">
                                        <option value="">-- Pilih Akun Pendapatan/Lainnya --</option>
                                        @foreach($allAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 4px 4px;">
                                    <select name="jobs[]" class="searchable-select" style="width: 100%;">
                                        <option value="">-- Tanpa Job --</option>
                                        @foreach($jobs as $job)
                                            <option value="{{ $job->id }}">{{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="text" name="line_desc[]" placeholder="Opsional" style="width: 100%;">
                                </td>
                                <td style="padding: 4px 4px;">
                                    <input type="hidden" name="amounts[]" class="input-amount" value="0">
                                    <input type="text" class="input-amount-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
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
                                <td style="padding: 12px 8px; text-align: right; font-weight: 700; color: #10b981; font-size: 1.1rem;" id="displayGrandTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Akan menjurnal Debit Kas/Bank dan Kredit pada Akun Rincian yang dipilih.
                </div>
                <div style="display: flex; gap: 8px;">
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
            const displayGrandTotal = document.getElementById('displayGrandTotal');

            const accountOptions = `@foreach($allAccounts as $acc)<option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach`;
            const jobOptions = `@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}</option>@endforeach`;

            window.calculateTotals = function() {
                let subtotal = 0;
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const amount = parseFloat(row.querySelector('.input-amount').value) || 0;
                    subtotal += amount;
                });

                displayGrandTotal.textContent = subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td style="padding: 4px 4px;">
                        <select name="income_accounts[]" required class="searchable-select" style="width: 100%;">
                            <option value="">-- Pilih Akun Pendapatan/Lainnya --</option>
                            ${accountOptions}
                        </select>
                    </td>
                    <td style="padding: 4px 4px;">
                        <select name="jobs[]" class="searchable-select" style="width: 100%;">
                            <option value="">-- Tanpa Job --</option>
                            ${jobOptions}
                        </select>
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="text" name="line_desc[]" placeholder="Opsional" style="width: 100%;">
                    </td>
                    <td style="padding: 4px 4px;">
                        <input type="hidden" name="amounts[]" class="input-amount" value="0">
                        <input type="text" class="input-amount-display" value="0" required style="width: 100%; text-align: right;" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
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

                // Initialize TomSelect on new row
                const newSelects = tr.querySelectorAll('.searchable-select');
                newSelects.forEach(selectEl => {
                    new TomSelect(selectEl, {
                        create: false,
                        sortField: {
                            field: "text",
                            direction: "asc"
                        }
                    });
                });
            });

            function attachRowListeners(row) {
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

            // Initialize TomSelect on initial selects
            new TomSelect('#receive_to', {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });

            document.querySelectorAll('.searchable-select').forEach(el => {
                new TomSelect(el, {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    }
                });
            });
        });
    </script>
</x-app-layout>
