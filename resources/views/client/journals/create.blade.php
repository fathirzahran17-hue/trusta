<x-app-layout>
    @section('title', 'Buat Jurnal Umum')
    @section('subtitle', 'Pencatatan transaksi akuntansi manual baru')

    <div class="mb-4 flex justify-between items-center">
        <a href="{{ route('client.journals.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Jurnal
        </a>
        <div class="flex items-center gap-2">
            <span class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700 font-mono">
                {{ $refNumber }}
            </span>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('client.journals.store') }}" method="POST" id="journalForm">
        @csrf

        {{-- Header Form Card --}}
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 mb-5 shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Nomor Referensi <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="ref_number" value="{{ old('ref_number', $refNumber) }}" required
                           class="w-full bg-slate-800/80 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:border-trusta-teal focus:ring-1 focus:ring-trusta-teal focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Tanggal Transaksi <span class="text-red-400">*</span>
                    </label>
                    <input type="date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required
                           class="w-full bg-slate-800/80 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-trusta-teal focus:ring-1 focus:ring-trusta-teal focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Keterangan Jurnal <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="description" value="{{ old('description') }}" required placeholder="Contoh: Setor Modal / Penyesuaian"
                           class="w-full bg-slate-800/80 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-trusta-teal focus:ring-1 focus:ring-trusta-teal focus:outline-none">
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl overflow-hidden shadow-lg mb-5">
            <div class="px-5 py-3.5 border-b border-slate-800 flex justify-between items-center bg-slate-800/40">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-trusta-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Rincian Akun (Debit & Kredit)
                </h3>
                <span class="text-xs text-slate-400">Minimal 2 baris (Debit harus sama dengan Kredit)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="journalTable">
                    <thead class="text-xs uppercase bg-slate-800/80 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="px-4 py-3 font-semibold w-[28%]">Akun Perkiraan <span class="text-red-400">*</span></th>
                            <th class="px-4 py-3 font-semibold w-[22%]">Keterangan Baris</th>
                            <th class="px-4 py-3 font-semibold w-[16%]">Kartu Job</th>
                            <th class="px-4 py-3 font-semibold text-right w-[15%]">Debit (Rp)</th>
                            <th class="px-4 py-3 font-semibold text-right w-[15%]">Kredit (Rp)</th>
                            <th class="px-3 py-3 w-[4%] text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="journalBody" class="divide-y divide-slate-800/70">
                        {{-- Default 2 Baris Awal --}}
                        @for($i = 0; $i < 2; $i++)
                            <tr class="item-row hover:bg-slate-800/30 transition-colors">
                                <td class="px-3 py-2.5">
                                    <select name="accounts[]" required class="searchable-select w-full">
                                        <option value="">-- Pilih Akun --</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>
                                                {{ $acc->code }} - {{ $acc->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="text" name="row_descriptions[]" placeholder="Opsional"
                                           class="w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-white placeholder-slate-500 focus:border-trusta-teal focus:outline-none">
                                </td>
                                <td class="px-3 py-2.5">
                                    <select name="jobs[]" class="searchable-select w-full">
                                        <option value="">- Tanpa Job -</option>
                                        @foreach($jobs as $job)
                                            <option value="{{ $job->id }}">
                                                {{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="hidden" name="debits[]" class="input-debit-hidden" value="0">
                                    <input type="text" class="input-debit-display w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-right text-emerald-400 font-mono font-semibold focus:border-trusta-teal focus:outline-none"
                                           value="0"
                                           onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="hidden" name="credits[]" class="input-credit-hidden" value="0">
                                    <input type="text" class="input-credit-display w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-right text-rose-400 font-mono font-semibold focus:border-trusta-teal focus:outline-none"
                                           value="0"
                                           onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <button type="button" class="btn-remove-row text-slate-500 hover:text-red-400 transition-colors p-1" title="Hapus Baris">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot class="bg-slate-800/40 border-t border-slate-700">
                        <tr>
                            <td colspan="6" class="px-4 py-3">
                                <button type="button" id="btnAddRow" class="inline-flex items-center gap-1.5 text-xs font-semibold text-trusta-teal hover:text-teal-300 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    Tambah Baris Akun
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Summary and Action Bar --}}
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex flex-wrap items-center gap-6">
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-lg px-4 py-2.5 min-w-[170px]">
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-0.5">Total Debit</div>
                    <div class="text-base font-bold text-emerald-400 font-mono" id="totalDebit">Rp 0</div>
                </div>

                <div class="bg-slate-800/60 border border-slate-700/60 rounded-lg px-4 py-2.5 min-w-[170px]">
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-0.5">Total Kredit</div>
                    <div class="text-base font-bold text-rose-400 font-mono" id="totalCredit">Rp 0</div>
                </div>

                <div id="balanceStatusContainer">
                    <div id="balanceStatus" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                <a href="{{ route('client.journals.index') }}"
                   class="px-4 py-2 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 text-xs font-medium transition-colors">
                    Batal
                </a>
                <button type="submit" id="btnSaveJournal"
                        class="px-5 py-2 rounded-lg bg-trusta-teal hover:bg-teal-400 text-slate-950 text-xs font-bold transition-all shadow-lg shadow-teal-950/40 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Jurnal
                </button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('journalBody');
            const btnAddRow = document.getElementById('btnAddRow');
            const totalDebitEl = document.getElementById('totalDebit');
            const totalCreditEl = document.getElementById('totalCredit');
            const balanceStatusEl = document.getElementById('balanceStatus');
            const btnSave = document.getElementById('btnSaveJournal');

            const accountOptions = `@foreach($accounts as $acc)<option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach`;
            const jobOptions = `@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}</option>@endforeach`;

            // Hitung total Debit & Credit
            window.calculateTotal = function() {
                let totalD = 0;
                let totalC = 0;

                document.querySelectorAll('.input-debit-hidden').forEach(input => {
                    let val = parseFloat(input.value) || 0;
                    totalD += val;
                });

                document.querySelectorAll('.input-credit-hidden').forEach(input => {
                    let val = parseFloat(input.value) || 0;
                    totalC += val;
                });

                totalDebitEl.textContent = 'Rp ' + totalD.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                totalCreditEl.textContent = 'Rp ' + totalC.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

                let diff = Math.abs(totalD - totalC);
                if (diff < 0.01 && totalD > 0) {
                    balanceStatusEl.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/10 border border-emerald-500/30 text-emerald-400';
                    balanceStatusEl.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Status: BALANCE (Seimbang)';
                    btnSave.disabled = false;
                    btnSave.classList.remove('opacity-50', 'cursor-not-allowed');
                } else if (totalD === 0 && totalC === 0) {
                    balanceStatusEl.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700';
                    balanceStatusEl.innerHTML = 'Silakan isi nominal debit & kredit';
                    btnSave.disabled = true;
                    btnSave.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    balanceStatusEl.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/10 border border-rose-500/30 text-rose-400';
                    balanceStatusEl.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg> SELISIH: Rp ' + diff.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                    btnSave.disabled = true;
                    btnSave.classList.add('opacity-50', 'cursor-not-allowed');
                }
            };

            function initSelect(element) {
                if (!element || element.tomselect) return;
                new TomSelect(element, {
                    create: false,
                    sortField: { field: "text", direction: "asc" }
                });
            }

            function updateRemoveButtons() {
                const rows = document.querySelectorAll('.item-row');
                const btns = document.querySelectorAll('.btn-remove-row');
                if (rows.length <= 2) {
                    btns.forEach(btn => {
                        btn.disabled = true;
                        btn.classList.add('opacity-30', 'cursor-not-allowed');
                    });
                } else {
                    btns.forEach(btn => {
                        btn.disabled = false;
                        btn.classList.remove('opacity-30', 'cursor-not-allowed');
                    });
                }
            }

            function attachRowListeners(row) {
                const btnRemove = row.querySelector('.btn-remove-row');
                btnRemove.addEventListener('click', function() {
                    if (document.querySelectorAll('.item-row').length > 2) {
                        row.remove();
                        calculateTotal();
                        updateRemoveButtons();
                    }
                });
            }

            // Inisialisasi baris awal
            document.querySelectorAll('.item-row').forEach(row => {
                attachRowListeners(row);
                row.querySelectorAll('.searchable-select').forEach(sel => initSelect(sel));
            });
            updateRemoveButtons();

            // Tambah baris baru
            btnAddRow.addEventListener('click', function() {
                const tr = document.createElement('tr');
                tr.className = 'item-row hover:bg-slate-800/30 transition-colors';
                tr.innerHTML = `
                    <td class="px-3 py-2.5">
                        <select name="accounts[]" required class="searchable-select w-full">
                            <option value="">-- Pilih Akun --</option>
                            ${accountOptions}
                        </select>
                    </td>
                    <td class="px-3 py-2.5">
                        <input type="text" name="row_descriptions[]" placeholder="Opsional"
                               class="w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-white placeholder-slate-500 focus:border-trusta-teal focus:outline-none">
                    </td>
                    <td class="px-3 py-2.5">
                        <select name="jobs[]" class="searchable-select w-full">
                            <option value="">- Tanpa Job -</option>
                            ${jobOptions}
                        </select>
                    </td>
                    <td class="px-3 py-2.5">
                        <input type="hidden" name="debits[]" class="input-debit-hidden" value="0">
                        <input type="text" class="input-debit-display w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-right text-emerald-400 font-mono font-semibold focus:border-trusta-teal focus:outline-none"
                               value="0"
                               onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                    </td>
                    <td class="px-3 py-2.5">
                        <input type="hidden" name="credits[]" class="input-credit-hidden" value="0">
                        <input type="text" class="input-credit-display w-full bg-slate-800/60 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-right text-rose-400 font-mono font-semibold focus:border-trusta-teal focus:outline-none"
                               value="0"
                               onkeyup="formatRupiah(this, this.previousElementSibling); calculateTotal()">
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <button type="button" class="btn-remove-row text-slate-500 hover:text-red-400 transition-colors p-1" title="Hapus Baris">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                `;
                tableBody.appendChild(tr);
                attachRowListeners(tr);
                tr.querySelectorAll('.searchable-select').forEach(sel => initSelect(sel));
                updateRemoveButtons();
            });

            calculateTotal();
        });
    </script>
</x-app-layout>
