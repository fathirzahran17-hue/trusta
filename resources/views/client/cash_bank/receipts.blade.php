<x-app-layout>
    @section('title', 'Penerimaan Kas & Bank')
    @section('subtitle', 'Catat dana masuk yang bukan dari faktur penjualan')

    <div class="mb-4">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Kas & Bank
        </a>
    </div>

    <form action="{{ route('client.cash-bank.receipts.store') }}" method="POST">
        @csrf
        
        <div class="doc-card">
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Informasi Penerimaan</h3>
                </div>

                <div class="form-grid form-cols-3">
                    <div>
                        <label for="receive_to">Terima Ke Akun (Kas/Bank) <span class="req">*</span></label>
                        <select name="receive_to" id="receive_to" required>
                            <option value="">-- Pilih Akun --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('receive_to') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="transaction_date">Tanggal <span class="req">*</span></label>
                        <input type="date" name="transaction_date" id="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label for="ref_number">No. Referensi (Otomatis jika kosong)</label>
                        <input type="text" name="ref_number" id="ref_number" value="{{ old('ref_number') }}">
                    </div>
                </div>

                <div class="form-grid form-cols-1-2">
                    <div>
                        <label for="payer">Dari (Pengirim)</label>
                        <input type="text" name="payer" id="payer" value="{{ old('payer') }}" placeholder="Nama pengirim dana...">
                    </div>
                    <div>
                        <label for="description">Keterangan Utama <span class="req">*</span></label>
                        <input type="text" name="description" id="description" value="{{ old('description') }}" required placeholder="Keterangan umum transaksi ini...">
                    </div>
                </div>
            </div>

            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Rincian Akun (Dari mana dana berasal?)</h3>
                </div>

                <div class="line-wrap">
                    <table class="line-items">
                        <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                            <tr>
                                <th style="width: 30%;">Pilih Akun (Lawan)</th>
                                <th style="width: 20%;">Kartu Job</th>
                                <th style="width: 30%;">Keterangan Baris</th>
                                <th style="width: 150px;" class="r">Jumlah (Rp)</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody" class="divide-y divide-gray-700/50">
                            <tr class="item-row">
                                <td class="col-account">
                                    <select name="income_accounts[]" required class="searchable-select">
                                        <option value="">-- Pilih Akun Pendapatan/Lainnya --</option>
                                        @foreach($allAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="col-job">
                                    <select name="jobs[]" class="searchable-select">
                                        <option value="">-- Tanpa Job --</option>
                                        @foreach($jobs as $job)
                                            <option value="{{ $job->id }}">{{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="col-desc">
                                    <input type="text" name="line_desc[]" placeholder="Opsional">
                                </td>
                                <td class="col-amount">
                                    <input type="hidden" name="amounts[]" class="input-amount" value="0">
                                    <input type="text" class="input-amount-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
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
                <div class="doc-summary doc-summary-end">@include('partials.doc-totals', ['label' => 'Total Penerimaan'])</div>
            </div>

            <div class="doc-actions">
                <div class="doc-actions-info">
                    Akan menjurnal Debit Kas/Bank dan Kredit pada Akun Rincian yang dipilih.
                </div>
                <span class="doc-actions-total">Total <b data-total-mirror>0,00</b></span>
                <div class="doc-actions-buttons">
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

                displayGrandTotal.textContent = window.fmtAmount(subtotal);
            }

            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row';
                tr.innerHTML = `
                    <td class="col-account">
                        <select name="income_accounts[]" required class="searchable-select">
                            <option value="">-- Pilih Akun Pendapatan/Lainnya --</option>
                            ${accountOptions}
                        </select>
                    </td>
                    <td class="col-job">
                        <select name="jobs[]" class="searchable-select">
                            <option value="">-- Tanpa Job --</option>
                            ${jobOptions}
                        </select>
                    </td>
                    <td class="col-desc">
                        <input type="text" name="line_desc[]" placeholder="Opsional">
                    </td>
                    <td class="col-amount">
                        <input type="hidden" name="amounts[]" class="input-amount" value="0">
                        <input type="text" class="input-amount-display" value="0" required onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotals()">
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
