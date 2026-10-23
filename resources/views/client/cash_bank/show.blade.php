<x-app-layout>
    @section('title', 'Kelola ' . $account->name)
    @section('subtitle', 'Rekonsiliasi dan catat transaksi langsung dari akun kas/bank')

    <div class="mb-4 flex justify-between items-center">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Kas & Bank
        </a>
    </div>

    @if(session('success'))
        <div class="mb-3 bg-green-500/10 border-l-3 border-green-500 p-3 text-green-400 rounded-r-lg text-xs">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    {{-- Compact Input Section (membeku: hanya riwayat mutasi yang ikut bergulir) --}}
    <div class="cb-sticky">
    <div class="cb-card" style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; margin-bottom: 16px;" x-data="{ tab: 'spend' }">
        <div style="display: flex; align-items: stretch;">
            {{-- Left: Account Info (compact) --}}
            <div style="width: 215px; padding: 14px 16px; border-right: 1px solid rgba(255,255,255,0.06); display: flex; flex-direction: column; justify-content: center; flex-shrink: 0;">
                <p style="font-size: 0.65rem; font-family: monospace; color: #64748b; margin-bottom: 2px;">{{ $account->code }}</p>
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 10px;">{{ $account->name }}</h3>
                <p style="font-size: 0.55rem; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 2px;">Saldo Berjalan</p>
                <p style="font-size: 1.15rem; white-space: nowrap; font-weight: 700; color: {{ $account->balance < 0 ? '#ef4444' : 'var(--trusta-teal)' }}; font-variant-numeric: tabular-nums;">Rp {{ number_format($account->balance, 2, ',', '.') }}</p>
            </div>

            {{-- Right: Compact Form --}}
            <div style="flex: 1; min-width: 0;">
                {{-- Tabs --}}
                <style>
                    .cashbank-tab {
                        flex: 1;
                        padding: 10px 8px !important;
                        font-size: 0.72rem !important;
                        font-weight: 600;
                        cursor: pointer;
                        border: none !important;
                        border-bottom: 2px solid transparent !important;
                        background: transparent !important;
                        transition: all 0.15s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                        color: #64748b;
                    }
                    .cashbank-tab.spend-active {
                        background: rgba(239,68,68,0.06) !important;
                        color: #f87171 !important;
                        border-bottom: 2px solid #ef4444 !important;
                    }
                    .cashbank-tab.receive-active {
                        background: rgba(34,197,94,0.06) !important;
                        color: #4ade80 !important;
                        border-bottom: 2px solid #22c55e !important;
                    }
                </style>
                <div style="display: flex; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <button type="button" @click="tab = 'spend'" class="cashbank-tab" :class="{ 'spend-active': tab === 'spend' }">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Keluarkan Uang
                    </button>
                    <button type="button" @click="tab = 'receive'" class="cashbank-tab" :class="{ 'receive-active': tab === 'receive' }">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Terima Uang
                    </button>
                </div>

                {{-- Form --}}
                <form action="{{ route('client.cash-bank.transaction', $account->id) }}" method="POST" style="padding: 12px 16px;" id="cbForm">
                    @csrf
                    <input type="hidden" name="transaction_type" x-model="tab">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label style="display: block; margin-bottom: 3px;">Tanggal <span style="color:#f87171">*</span></label>
                            <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 3px;">Kontak</label>
                            <input type="text" name="contact_name" placeholder="Opsional" value="{{ old('contact_name') }}">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 3px;">Keterangan <span style="color:#f87171">*</span></label>
                            <input type="text" name="description" required placeholder="Untuk keperluan apa?" value="{{ old('description') }}">
                        </div>
                    </div>

                    {{-- Akun lawan: boleh lebih dari satu (split) --}}
                    <div style="font-size:.7rem; color:#94a3b8; margin-bottom:4px;" x-text="tab === 'spend' ? 'Uang dikeluarkan untuk (Debit akun lawan):' : 'Uang diterima dari (Kredit akun lawan):'"></div>
                    <div id="cbLines">
                        <template id="cbLineTpl">
                            <div class="cb-line" style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 28px; gap: 10px; margin-bottom: 6px; align-items: end;">
                                <div>
                                    <label class="cb-lbl" style="display: block; margin-bottom: 3px;">Akun Lawan <span style="color:#f87171">*</span></label>
                                    <select name="contra_account_id[]" class="searchable-select" required>
                                        <option value="">-- Pilih Akun --</option>
                                        @foreach($allAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }}>{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="cb-lbl" style="display: block; margin-bottom: 3px;">Kartu Job</label>
                                    <select name="project_job_id[]" class="searchable-select">
                                        <option value="">-- Tanpa Job --</option>
                                        @foreach($jobs as $job)
                                            <option value="{{ $job->id }}">{{ $job->code ? $job->code . ' - ' : '' }}{{ $job->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="cb-lbl" style="display: block; margin-bottom: 3px;">Nominal (Rp) <span style="color:#f87171">*</span></label>
                                    <input type="hidden" name="amounts[]" class="cb-amt-hidden" value="">
                                    <input type="text" class="cb-amt" required placeholder="0,00" inputmode="decimal" autocomplete="off" style="text-align: right; font-variant-numeric: tabular-nums; width: 100%;" onkeyup="formatRupiah(this, this.previousElementSibling); cbTotal()">
                                </div>
                                <button type="button" class="cb-del" aria-label="Hapus baris" style="height:32px; border:none; background:none; color:#f87171; font-size:1.2rem; cursor:pointer;">&times;</button>
                            </div>
                        </template>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; justify-content: space-between; margin-top: 6px;">
                        <button type="button" id="cbAdd" style="font-size:.75rem; color:var(--accent, #14b8a6); background:none; border:none; cursor:pointer;">+ Tambah akun lawan (split)</button>
                        <div style="font-size:.8rem; color:#94a3b8;">Total <b id="cbTotal" style="color:#fff; font-size:1rem;">Rp 0,00</b></div>
                        <style>
                            .cashbank-submit { padding: 6px 16px !important; color: #fff !important; font-size: 0.75rem !important; font-weight: 600; border: none !important; border-radius: 6px !important; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 4px; transition: all 0.15s; height: 32px; }
                            .cashbank-submit.spend-btn { background: #ef4444 !important; }
                            .cashbank-submit.spend-btn:hover { background: #dc2626 !important; }
                            .cashbank-submit.receive-btn { background: #22c55e !important; }
                            .cashbank-submit.receive-btn:hover { background: #16a34a !important; }
                            div.cb-line:first-of-type .cb-del { visibility: hidden; }
                            div.cb-line:not(:first-of-type) .cb-lbl { display: none !important; }
                        </style>
                        <button type="submit" class="cashbank-submit" :class="tab === 'spend' ? 'spend-btn' : 'receive-btn'">
                            <span x-text="tab === 'spend' ? 'Catat Pengeluaran' : 'Catat Penerimaan'"></span>
                            <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>{{-- /cb-sticky --}}

    {{-- Mutation Table (Ledger) - Focus Area --}}
    <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
        <div style="padding: 10px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; background: rgba(30,41,59,0.5);">
            <h3 style="font-size: 0.85rem; font-weight: 600; color: #fff; margin: 0;">Riwayat Mutasi</h3>
            <span style="font-size: 0.6rem; color: #64748b; background: rgba(15,23,42,0.5); padding: 3px 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.06);">Otomatis Terhubung Jurnal</span>
        </div>
        
        @include('partials.ledger-filter')

        @if($totalCount > 0 || (float) $account->opening_balance != 0.0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-300">
                    <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-6 py-4 font-semibold w-32">Tanggal</th>
                            <th class="px-6 py-4 font-semibold">Referensi</th>
                            <th class="px-6 py-4 font-semibold">Keterangan</th>
                            <th class="px-6 py-4 font-semibold text-right w-36">Masuk (Debit)</th>
                            <th class="px-6 py-4 font-semibold text-right w-36">Keluar (Kredit)</th>
                            <th class="px-6 py-4 font-semibold text-right w-40 text-trusta-teal">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        @php
                            $runningBalance = (float) $periodOpening;
                            $calcMutations = [];
                            $reversed = $mutations->reverse();
                            
                            foreach($reversed as $mut) {
                                if ($account->normal_balance == 'debit') {
                                    $runningBalance = $runningBalance + $mut->debit - $mut->credit;
                                } else {
                                    $runningBalance = $runningBalance + $mut->credit - $mut->debit;
                                }
                                
                                $mut->current_balance = $runningBalance;
                                $calcMutations[] = $mut;
                            }
                            $calcMutations = array_reverse($calcMutations);
                        @endphp
                        
                        @foreach ($calcMutations as $mut)
                            <tr class="hover:bg-gray-700/20 transition-colors">
                                <td class="px-6 py-4 text-gray-400">
                                    {{ \Carbon\Carbon::parse($mut->journal->transaction_date)->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('client.journals.show', $mut->journal->id) }}" class="text-trusta-teal hover:underline" title="Lihat Jurnal">
                                        {{ $mut->journal->ref_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-white">
                                    {{ $mut->description ?: $mut->journal->description }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-green-400">
                                    {{ $mut->debit > 0 ? '+ ' . number_format($mut->debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-red-400">
                                    {{ $mut->credit > 0 ? '- ' . number_format($mut->credit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right font-bold tabular-nums {{ $mut->current_balance < 0 ? 'text-red-400' : 'text-white' }}">
                                    Rp {{ number_format($mut->current_balance, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        @if(count($calcMutations) === 0)
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada mutasi pada periode ini. Ubah tanggal atau pilih "Semua".</td></tr>
                        @endif
                        <!-- Saldo awal periode (di dasar karena urutan terbaru di atas) -->
                        <tr style="background: rgba(30,41,59,0.3);">
                            <td class="px-6 py-4 text-gray-500">{{ $period['from'] ? \Carbon\Carbon::parse($period['from'])->format('d/m/Y') : '-' }}</td>
                            <td class="px-6 py-4 text-gray-500">-</td>
                            <td class="px-6 py-4 font-medium italic text-gray-400">{{ $period['from'] ? 'Saldo Awal Periode' : 'Saldo Awal (Opening Balance)' }}</td>
                            <td class="px-6 py-4 text-right text-gray-500">-</td>
                            <td class="px-6 py-4 text-right text-gray-500">-</td>
                            <td class="px-6 py-4 text-right font-bold text-white tabular-nums">
                                Rp {{ number_format($periodOpening, 2, ',', '.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div style="padding: 40px 16px; text-align: center;">
                <svg style="width: 40px; height: 40px; margin: 0 auto 12px; color: #334155;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3 style="font-size: 0.9rem; font-weight: 600; color: #fff; margin-bottom: 4px;">Belum ada transaksi</h3>
                <p style="color: #64748b; font-size: 0.78rem;">Silakan catat Pengeluaran atau Penerimaan dari form di atas.</p>
            </div>
        @endif
    </div>

    <script>
        function cbTotal() {
            var t = 0;
            document.querySelectorAll('#cbLines .cb-amt-hidden').forEach(function (h) { t += parseFloat(h.value) || 0; });
            document.getElementById('cbTotal').textContent = window.fmtRp(t);
        }
        document.addEventListener('DOMContentLoaded', function() {
            var wrap = document.getElementById('cbLines'), tpl = document.getElementById('cbLineTpl');
            function initSelects(root) {
                root.querySelectorAll('.searchable-select').forEach(function (el) {
                    new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
                });
            }
            function addLine(accountId, jobId, amount) {
                var node = tpl.content.firstElementChild.cloneNode(true);
                wrap.appendChild(node);
                if (accountId) node.querySelector('[name="contra_account_id[]"]').value = accountId;
                if (jobId) node.querySelector('[name="project_job_id[]"]').value = jobId;
                initSelects(node);
                if (amount) { var d = node.querySelector('.cb-amt'); d.value = String(amount); formatRupiah(d, node.querySelector('.cb-amt-hidden')); }
                cbTotal();
            }
            document.getElementById('cbAdd').addEventListener('click', function () { addLine(); });
            wrap.addEventListener('click', function (e) { var b = e.target.closest('.cb-del'); if (b) { b.closest('.cb-line').remove(); cbTotal(); } });
            wrap.addEventListener('input', cbTotal);
            document.getElementById('cbForm').addEventListener('submit', function (e) {
                var ok = true;
                wrap.querySelectorAll('.cb-line').forEach(function (l) {
                    var h = l.querySelector('.cb-amt-hidden'), d = l.querySelector('.cb-amt');
                    h.value = window.parseMoney(d.value); if (!(parseFloat(h.value) > 0)) ok = false;
                });
                if (!ok) { e.preventDefault(); alert('Isi nominal lebih dari 0 pada setiap baris akun lawan.'); }
            });
            @php($oldLines = old('contra_account_id', []))
            @if(is_array($oldLines) && count($oldLines))
                @foreach($oldLines as $i => $acc)
                    addLine(@json($acc), @json(old('project_job_id.' . $i)), @json(old('amounts.' . $i)));
                @endforeach
            @else
                addLine();
            @endif
        });
    </script>
</x-app-layout>
