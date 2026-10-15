<x-app-layout>
@section('title', 'Ubah Jurnal Umum')
@section('subtitle', 'Ubah Jurnal Umum: ' . $journal->ref_number)

<style>
    .jv-container {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 600px;
        position: relative;
    }
    .dark .jv-container {
        background: var(--trusta-surface);
        border-color: var(--trusta-border);
    }
    
    .jv-header {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
    }
    .dark .jv-header {
        border-bottom-color: var(--trusta-border);
        background: var(--trusta-navy-light);
    }
    
    .jv-btn-green {
        background: #22c55e;
        color: white;
        padding: 10px 16px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .jv-tab-active {
        background: #fff;
        border-top: 3px solid #ec4899;
        border-right: 1px solid #e5e7eb;
        padding: 10px 16px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dark .jv-tab-active {
        background: var(--trusta-surface);
        border-right-color: var(--trusta-border);
    }
    
    .jv-header-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        padding-right: 16px;
        gap: 8px;
    }
    
    .jv-icon-btn {
        padding: 6px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        background: #fff;
        color: #3b82f6;
        cursor: pointer;
    }
    .dark .jv-icon-btn {
        background: transparent;
        border-color: var(--trusta-border);
    }
    
    .jv-main-form {
        padding: 20px;
        display: flex;
        gap: 20px;
        position: relative;
    }
    
    .jv-form-fields {
        flex: 1;
        display: grid;
        grid-template-columns: 100px 1fr 100px 1fr;
        gap: 16px;
        align-items: center;
    }
    
    .jv-label {
        font-size: 0.9rem;
        color: #374151;
    }
    .dark .jv-label { color: #d1d5db; }
    
    .jv-input {
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.9rem;
        width: 100%;
    }
    .dark .jv-input {
        background: var(--trusta-navy-light);
        border-color: var(--trusta-border);
        color: #f3f4f6;
    }
    
    .jv-right-sidebar {
        width: 48px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .jv-action-btn {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 1px solid #d1d5db;
        background: #f8fafc;
        color: #64748b;
    }
    .dark .jv-action-btn {
        border-color: var(--trusta-border);
        background: var(--trusta-navy-light);
    }
    
    /* Document Layout */
    .jv-doc-area {
        display: flex;
        flex: 1;
        border-top: 1px solid #e5e7eb;
    }
    .dark .jv-doc-area { border-top-color: var(--trusta-border); }
    
    .jv-doc-sidebar {
        width: 48px;
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        background: #f8fafc;
    }
    .dark .jv-doc-sidebar {
        border-right-color: var(--trusta-border);
        background: var(--trusta-navy-light);
    }
    .jv-doc-tab {
        width: 100%;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-bottom: 1px solid #e5e7eb;
    }
    .dark .jv-doc-tab { border-bottom-color: var(--trusta-border); }
    .jv-doc-tab.active {
        background: #fff;
        border-left: 3px solid #ec4899;
        color: #ec4899;
    }
    .dark .jv-doc-tab.active { background: var(--trusta-surface); }
    
    .jv-doc-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 16px;
    }
    
    .jv-search-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        position: relative;
    }
    
    .jv-search-input {
        flex: 1;
        padding: 8px 12px 8px 36px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.95rem;
    }
    .dark .jv-search-input {
        background: var(--trusta-navy-light);
        border-color: var(--trusta-border);
        color: #fff;
    }
    
    /* Autocomplete Dropdown */
    .jv-autocomplete {
        position: absolute;
        top: 100%;
        left: 0;
        width: calc(100% - 200px); /* Leave space for title */
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 50;
        display: none;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .dark .jv-autocomplete {
        background: var(--trusta-surface);
        border-color: var(--trusta-border);
    }
    
    .jv-autocomplete-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
    }
    .dark .jv-autocomplete-item { border-bottom-color: var(--trusta-border); }
    .jv-autocomplete-item:hover { background: #f8fafc; }
    .dark .jv-autocomplete-item:hover { background: rgba(255,255,255,0.05); }
    
    .jv-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e5e7eb;
    }
    .dark .jv-table { border-color: var(--trusta-border); }
    
    .jv-table th {
        background: #475569;
        color: #fff;
        padding: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        text-align: left;
        border-right: 1px solid #64748b;
        cursor: pointer;
        position: relative;
    }
    .dark .jv-table th {
        background: #1e293b;
        border-right-color: #334155;
    }
    .jv-table th::after {
        content: "↕";
        position: absolute;
        right: 8px;
        opacity: 0.5;
    }
    
    .jv-table td {
        padding: 10px;
        border-bottom: 1px solid #e5e7eb;
        border-right: 1px solid #e5e7eb;
        font-size: 0.9rem;
    }
    .dark .jv-table td { border-color: var(--trusta-border); }
    
    .jv-cell-input {
        width: 100%;
        border: none;
        background: transparent;
        text-align: right;
        outline: none;
        color: inherit;
    }
    .jv-cell-input:focus {
        border-bottom: 1px solid var(--trusta-teal);
    }
    
    .jv-footer {
        padding: 16px;
        display: flex;
        justify-content: flex-end;
        gap: 16px;
    }
    
    .jv-summary-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        padding: 12px 20px;
        min-width: 200px;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
    }
    .dark .jv-summary-box {
        background: var(--trusta-navy-light);
        border-color: var(--trusta-border);
    }
</style>

@if(session('error'))
    <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; border-left: 4px solid #ef4444;">
        {!! session('error') !!}
    </div>
@endif

<form action="{{ route('client.journals.update', $journal->id) }}" method="POST" id="journalForm">
    @csrf
    @method('PUT')
    
    <div class="jv-container">
        <!-- Header Toolbar -->
        <div class="jv-header">
            <button type="button" class="jv-btn-green" title="Batal & Kembali" onclick="window.location.href='{{ route('client.journals.show', $journal->id) }}'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </button>
            <div class="jv-tab-active">
                Ubah Jurnal 
                <span style="font-size: 0.8rem; color: #94a3b8; cursor: pointer;">✖</span>
            </div>
            
        </div>
        
        <!-- Main Form Info -->
        <div class="jv-main-form">
            <div class="jv-form-fields">
                <div class="jv-label">Tanggal <span style="color:red;">*</span></div>
                <div>
                    <input type="date" name="transaction_date" class="jv-input" value="{{ old('transaction_date', $journal->transaction_date->format('Y-m-d')) }}" required style="width: 150px;" />
                </div>
                
                <div class="jv-label" style="text-align: right;">Nomor # <span style="color:red;">*</span></div>
                <div style="display: flex; gap: 8px;">
                    <div style="width: 36px; height: 20px; background: #3b82f6; border-radius: 10px; position: relative;">
                        <div style="width: 16px; height: 16px; background: white; border-radius: 50%; position: absolute; right: 2px; top: 2px;"></div>
                    </div>
                    <input type="text" name="ref_number" class="jv-input" value="{{ old('ref_number', $journal->ref_number) }}" required />
                </div>
                
                <div class="jv-label">Keterangan <span style="color:red;">*</span></div>
                <div style="grid-column: 2 / span 3;">
                    <input type="text" name="description" class="jv-input" value="{{ old('description', $journal->description) }}" required placeholder="Jurnal Umum" style="width: 100%;" />
                </div>
            </div>
            
        </div>
        
        <!-- Document Area -->
        <div class="jv-doc-area">
            <div class="jv-doc-content" style="padding-left: 20px;">
            <div class="jv-doc-content" style="padding-left: 20px;">
                <!-- Table -->
                <div style="overflow-x: visible; min-height: 200px;">
                    <table class="jv-table" id="journalTable">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Akun Perkiraan</th>
                                <th style="width: 25%;">Keterangan</th>
                                <th style="width: 15%;">Kartu Job</th>
                                <th style="width: 15%; text-align: right;">Debit</th>
                                <th style="width: 15%; text-align: right;">Kredit</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="journalBody">
                            @foreach($journal->details as $detail)
                                <tr class="item-row">
                                    <td style="padding: 4px;">
                                        <select name="accounts[]" required class="jv-input searchable-select" style="width: 100%; border: none; background: transparent;">
                                            <option value="">-- Pilih Akun --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ $detail->account_id == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="padding: 4px;">
                                        <input type="text" name="row_descriptions[]" value="{{ $detail->description ?: $detail->account->name }}" style="width:100%; border:none; background:transparent; outline:none; color:inherit;">
                                    </td>
                                    <td style="padding: 4px;">
                                        <select name="jobs[]" class="jv-input searchable-select" style="width: 100%; border: none; background: transparent; color: #64748b;">
                                            <option value="">- Tanpa Job -</option>
                                            @foreach($jobs as $job)
                                                <option value="{{ $job->id }}" {{ $detail->project_job_id == $job->id ? 'selected' : '' }}>{{ $job->code ? $job->code.' - ' : '' }}{{ $job->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="padding: 4px;">
                                        <input type="hidden" name="debits[]" class="input-debit-hidden" value="{{ $detail->debit }}">
                                        <input type="text" class="jv-cell-input input-debit-display" value="{{ $detail->debit }}" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                                    </td>
                                    <td style="padding: 4px;">
                                        <input type="hidden" name="credits[]" class="input-credit-hidden" value="{{ $detail->credit }}">
                                        <input type="text" class="jv-cell-input input-credit-display" value="{{ $detail->credit }}" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                                    </td>
                                    <td style="text-align: center; padding: 4px;">
                                        <button type="button" class="btn-remove-row" style="color: #ef4444; background: transparent; border: none; cursor: pointer;">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-700 bg-gray-800/30 dark:border-gray-700">
                            <tr>
                                <td colspan="6" style="padding: 12px; background: #f8fafc;" class="dark:bg-[#1e293b]">
                                    <button type="button" id="btnAddRow" style="color: var(--trusta-teal); background: none; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Baris Jurnal
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Footer / Totals -->
        <div class="jv-footer">
            <div class="jv-summary-box">
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">Total Debit</div>
                <div style="font-size: 1.25rem; font-weight: 600; text-align: right;" id="totalDebit">Rp 0</div>
            </div>
            <div class="jv-summary-box">
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">Total Credit</div>
                <div style="font-size: 1.25rem; font-weight: 600; text-align: right;" id="totalCredit">Rp 0</div>
            </div>
        </div>
        <div id="balanceStatus" style="text-align: right; padding-right: 16px; padding-bottom: 16px; font-weight: 600;"></div>
        
        <!-- Big Save Button -->
        <div style="padding: 0 16px 20px 16px; display: flex; justify-content: flex-end; border-top: 1px solid #e5e7eb; margin-top: 10px; padding-top: 20px;" class="dark:border-slate-700">
            <button type="submit" id="btnSaveJournal" class="jv-btn-green" style="font-size: 1rem; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" style="margin-right: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Simpan Perubahan
            </button>
        </div>
    </div>
</form>

<script>
    // Format Rupiah
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

    const tableBody = document.getElementById('journalBody');
    const btnAddRow = document.getElementById('btnAddRow');

    const accountOptions = `@foreach($accounts as $acc)<option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach`;
    const jobOptions = `@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->code ? $job->code.' - ' : '' }}{{ $job->name }}</option>@endforeach`;

    // Add Row
    btnAddRow.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td style="padding: 4px;">
                <select name="accounts[]" required class="jv-input searchable-select" style="width: 100%; border: none; background: transparent;">
                    <option value="">-- Pilih Akun --</option>
                    ${accountOptions}
                </select>
            </td>
            <td style="padding: 4px;">
                <input type="text" name="row_descriptions[]" placeholder="Opsional" style="width:100%; border:none; background:transparent; outline:none; color:inherit;">
            </td>
            <td style="padding: 4px;">
                <select name="jobs[]" class="jv-input searchable-select" style="width: 100%; border: none; background: transparent; color: #64748b;">
                    <option value="">- Tanpa Job -</option>
                    ${jobOptions}
                </select>
            </td>
            <td style="padding: 4px;">
                <input type="hidden" name="debits[]" class="input-debit-hidden" value="0">
                <input type="text" class="jv-cell-input input-debit-display" value="0" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
            </td>
            <td style="padding: 4px;">
                <input type="hidden" name="credits[]" class="input-credit-hidden" value="0">
                <input type="text" class="jv-cell-input input-credit-display" value="0" onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
            </td>
            <td style="text-align: center; padding: 4px;">
                <button type="button" class="btn-remove-row" style="color: #ef4444; background: transparent; border: none; cursor: pointer;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
        attachRowListeners(tr);
        updateRemoveButtons();
        
        // Initialize TomSelect on new selects
        tr.querySelectorAll('.searchable-select').forEach(el => {
            new TomSelect(el, {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });
        });
    });

    function attachRowListeners(tr) {
        tr.querySelector('.btn-remove-row').addEventListener('click', function() {
            if (document.querySelectorAll('.item-row').length > 2) {
                tr.remove();
                calculateTotal();
                updateRemoveButtons();
            }
        });
        
        // Exclusivity debit/credit
        const displayInputs = tr.querySelectorAll('input[type="text"].jv-cell-input');
        displayInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.classList.contains('input-debit-display') && parseFloat(this.previousElementSibling.value) > 0) {
                    tr.querySelector('.input-credit-hidden').value = 0;
                    tr.querySelector('.input-credit-display').value = '0';
                } else if (this.classList.contains('input-credit-display') && parseFloat(this.previousElementSibling.value) > 0) {
                    tr.querySelector('.input-debit-hidden').value = 0;
                    tr.querySelector('.input-debit-display').value = '0';
                }
                calculateTotal();
            });
        });
    }

    function updateRemoveButtons() {
        const btns = document.querySelectorAll('.btn-remove-row');
        if (btns.length <= 2) {
            btns.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.3';
                btn.style.cursor = 'not-allowed';
            });
        } else {
            btns.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        }
    }

    // Attach listeners to existing rows
    document.querySelectorAll('#journalBody .item-row').forEach(tr => {
        let debitDisplay = tr.querySelector('.input-debit-display');
        if(debitDisplay) {
            formatRupiah(debitDisplay, debitDisplay.previousElementSibling);
        }
        let creditDisplay = tr.querySelector('.input-credit-display');
        if(creditDisplay) {
            formatRupiah(creditDisplay, creditDisplay.previousElementSibling);
        }
        attachRowListeners(tr);
    });

    // Calculate Totals
    window.calculateTotal = function() {
        let totalD = 0;
        let totalC = 0;

        document.querySelectorAll('.input-debit-hidden').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) totalD += val;
        });

        document.querySelectorAll('.input-credit-hidden').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) totalC += val;
        });

        document.getElementById('totalDebit').textContent = 'Rp ' + totalD.toLocaleString('id-ID');
        document.getElementById('totalCredit').textContent = 'Rp ' + totalC.toLocaleString('id-ID');

        const diff = Math.abs(totalD - totalC);
        const statusEl = document.getElementById('balanceStatus');
        const submitBtn = document.querySelector('button[type="submit"]');
        
        if (diff < 0.01 && totalD > 0) {
            statusEl.innerHTML = '<span style="color: #22c55e;">Status: BALANCE</span>';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        } else {
            statusEl.innerHTML = '<span style="color: #ef4444;">Status: TIDAK BALANCE (Selisih: Rp ' + diff.toLocaleString('id-ID') + ')</span>';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
        }
    }
    
    // Sort Table feature (Sort concept)
    window.sortTable = function(n) {
        var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        table = document.getElementById("journalTable");
        switching = true;
        dir = "asc"; 
        
        while (switching) {
            switching = false;
            rows = table.rows;
            
            for (i = 1; i < (rows.length - 1); i++) {
                if(rows[i].id === 'emptyRow') continue;
                
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[n];
                y = rows[i + 1].getElementsByTagName("TD")[n];
                
                if(!x || !y) continue;
                if(rows[i+1].id === 'emptyRow') continue;

                let valX = x.innerHTML.toLowerCase();
                let valY = y.innerHTML.toLowerCase();
                
                // Form input handler
                if(n === 2) { // description
                    let inputX = x.querySelector('input');
                    let inputY = y.querySelector('input');
                    if(inputX && inputY) {
                        valX = inputX.value.toLowerCase();
                        valY = inputY.value.toLowerCase();
                    }
                }
                else if(n === 3 || n === 4) { // debit/credit
                    let inputX = x.querySelector('.jv-cell-input');
                    let inputY = y.querySelector('.jv-cell-input');
                    if(inputX && inputY) {
                        valX = parseFloat(inputX.previousElementSibling.value);
                        valY = parseFloat(inputY.previousElementSibling.value);
                    }
                }

                if (dir == "asc") {
                    if (valX > valY) {
                        shouldSwitch = true;
                        break;
                    }
                } else if (dir == "desc") {
                    if (valX < valY) {
                        shouldSwitch = true;
                        break;
                    }
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
                switchcount ++;
            } else {
                if (switchcount == 0 && dir == "asc") {
                    dir = "desc";
                    switching = true;
                }
            }
        }
    }
    
    // Run initial calculation
    calculateTotal();
    updateRemoveButtons();
    
    document.addEventListener('DOMContentLoaded', function() {
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
