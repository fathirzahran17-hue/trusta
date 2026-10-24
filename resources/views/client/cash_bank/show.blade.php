<x-app-layout>
    @section('title', 'Kelola ' . $account->name)
    @section('subtitle', 'Rekonsiliasi dan catat transaksi langsung dari akun kas/bank')

    <div class="mb-2 flex justify-between items-center">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Kas & Bank
        </a>
    </div>

{{-- Form input ringkas & membeku: hanya riwayat mutasi yang ikut bergulir --}}
    <div class="cb-sticky">
    <div class="cb-card cb-compact" id="cbCard" style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px;" x-data="{ tab: 'spend' }">
        <style>
            .cb-compact input:not([type=checkbox]):not([type=radio]), .cb-compact select, .cb-compact .ts-control { padding: 4px 8px !important; font-size: .76rem !important; min-height: 28px !important; height: 28px; border-radius: 6px !important; }
            .cb-compact .ts-control { height: auto; }
            .cb-compact label { font-size: .6rem !important; margin: 0 0 1px !important; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
            .cb-head { display: flex; align-items: center; gap: 14px; padding: 6px 12px; border-bottom: 1px solid rgba(255,255,255,0.06); flex-wrap: wrap; }
            .cb-head .cb-acc { display: flex; align-items: baseline; gap: 8px; min-width: 0; }
            .cb-head .cb-code { font-family: monospace; font-size: .62rem; color: #64748b; }
            .cb-head .cb-name { font-size: .88rem; font-weight: 700; color: var(--text-strong, #fff); white-space: nowrap; }
            .cb-head .cb-bal { font-size: .88rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
            .cashbank-tab { padding: 4px 12px !important; font-size: .7rem !important; font-weight: 600; cursor: pointer; border: 1px solid transparent !important; border-radius: 999px !important; background: transparent !important; color: #64748b; display: inline-flex; align-items: center; gap: 5px; transition: all .15s; }
            .cashbank-tab.spend-active { background: rgba(239,68,68,0.12) !important; color: #f87171 !important; border-color: rgba(239,68,68,.4) !important; }
            .cashbank-tab.receive-active { background: rgba(34,197,94,0.12) !important; color: #4ade80 !important; border-color: rgba(34,197,94,.4) !important; }
            .cb-toggle { margin-left: auto; font-size: .66rem; color: #94a3b8; background: none; border: none; cursor: pointer; padding: 2px 6px; white-space: nowrap; }
            .cb-toggle:hover { color: var(--accent, #14b8a6); }
            .cb-collapsed .cb-body { display: none; }
            .cb-collapsed .cb-head { border-bottom: none; }
            .cashbank-submit { padding: 0 14px !important; color: #fff !important; font-size: .72rem !important; font-weight: 600; border: none !important; border-radius: 6px !important; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 4px; transition: all .15s; height: 28px; }
            .cashbank-submit.spend-btn { background: #ef4444 !important; }
            .cashbank-submit.spend-btn:hover { background: #dc2626 !important; }
            .cashbank-submit.receive-btn { background: #22c55e !important; }
            .cashbank-submit.receive-btn:hover { background: #16a34a !important; }
            div.cb-line:first-of-type .cb-del { visibility: hidden; }
            div.cb-line:not(:first-of-type) .cb-lbl { display: none !important; }
        </style>

        <div class="cb-head">
            <div class="cb-acc">
                <span class="cb-code">{{ $account->code }}</span>
                <span class="cb-name">{{ $account->name }}</span>
                <span class="cb-bal" style="color: {{ $account->balance < 0 ? '#ef4444' : 'var(--trusta-teal)' }};" title="Saldo berjalan">Rp {{ number_format($account->balance, 2, ',', '.') }}</span>
            </div>
            <div style="display:flex; gap:6px;">
                <button type="button" @click="tab = 'spend'" class="cashbank-tab" :class="{ 'spend-active': tab === 'spend' }">Keluarkan Uang</button>
                <button type="button" @click="tab = 'receive'" class="cashbank-tab" :class="{ 'receive-active': tab === 'receive' }">Terima Uang</button>
            </div>
            <button type="button" class="cb-toggle" id="cbToggle" title="Sembunyikan / tampilkan form agar riwayat lebih lega">▲ Sembunyikan form</button>
        </div>

        <div class="cb-body">
            <form action="{{ route('client.cash-bank.transaction', $account->id) }}" method="POST" style="padding: 8px 12px;" id="cbForm">
                @csrf
                <input type="hidden" name="transaction_type" x-model="tab">
                @if($period['preset'] === 'custom')
                    <input type="hidden" name="from" value="{{ $period['from'] }}"><input type="hidden" name="to" value="{{ $period['to'] }}">
                @else
                    <input type="hidden" name="range" value="{{ $period['preset'] }}">
                @endif

                <div style="display: grid; grid-template-columns: 130px 1fr 2fr; gap: 8px; margin-bottom: 6px;">
                    <div>
                        <label style="display: block;">Tanggal <span style="color:#f87171">*</span></label>
                        <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}">
                    </div>
                    <div>
                        <label style="display: block;">Kontak</label>
                        <input type="text" name="contact_name" placeholder="Opsional" value="{{ old('contact_name') }}">
                    </div>
                    <div>
                        <label style="display: block;">Keterangan <span style="color:#f87171">*</span></label>
                        <input type="text" name="description" required placeholder="Untuk keperluan apa?" value="{{ old('description') }}">
                    </div>
                </div>

                <div style="font-size:.62rem; color:#94a3b8; margin-bottom:2px;" x-text="tab === 'spend' ? 'Uang dikeluarkan untuk (Debit akun lawan):' : 'Uang diterima dari (Kredit akun lawan):'"></div>
                <div id="cbLines">
                    <template id="cbLineTpl">
                            <div class="cb-line" style="display: grid; grid-template-columns: 2fr 1.1fr 1fr 22px; gap: 8px; margin-bottom: 4px; align-items: end;">
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
                <div style="display: flex; gap: 10px; align-items: center; justify-content: space-between; margin-top: 4px;">
                    <button type="button" id="cbAdd" style="font-size:.7rem; color:var(--accent, #14b8a6); background:none; border:none; cursor:pointer; padding:0;">+ Tambah akun lawan (split)</button>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="font-size:.72rem; color:#94a3b8;">Total <b id="cbTotal" style="color:#fff; font-size:.9rem;">Rp 0,00</b></div>
                        <button type="submit" class="cashbank-submit" :class="tab === 'spend' ? 'spend-btn' : 'receive-btn'">
                            <span x-text="tab === 'spend' ? 'Catat Pengeluaran' : 'Catat Penerimaan'"></span>
                            <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>{{-- /cb-sticky --}}

    {{-- Mutation Table (Ledger) - Focus Area --}}
    <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
        <div style="padding: 6px 14px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; background: rgba(30,41,59,0.5);">
            <h3 style="font-size: 0.85rem; font-weight: 600; color: #fff; margin: 0;">Riwayat Mutasi</h3>
            <span style="font-size: 0.6rem; color: #64748b; background: rgba(15,23,42,0.5); padding: 3px 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.06);">Otomatis Terhubung Jurnal</span>
        </div>
        
        @include('partials.ledger-filter')

        @if($totalCount > 0 || (float) $account->opening_balance != 0.0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-300">
                    <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-4 py-2 font-semibold w-32">Tanggal</th>
                            <th class="px-4 py-2 font-semibold">Referensi</th>
                            <th class="px-4 py-2 font-semibold">Keterangan</th>
                            <th class="px-4 py-2 font-semibold text-right w-36">Masuk (Debit)</th>
                            <th class="px-4 py-2 font-semibold text-right w-36">Keluar (Kredit)</th>
                            <th class="px-4 py-2 font-semibold text-right w-40 text-trusta-teal">Saldo</th>
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
                                <td class="px-4 py-2 text-gray-400">
                                    {{ \Carbon\Carbon::parse($mut->journal->transaction_date)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('client.journals.show', $mut->journal->id) }}" class="text-trusta-teal hover:underline" title="Lihat Jurnal">
                                        {{ $mut->journal->ref_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-white">
                                    {{ $mut->description ?: $mut->journal->description }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-green-400">
                                    {{ $mut->debit > 0 ? '+ ' . number_format($mut->debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-red-400">
                                    {{ $mut->credit > 0 ? '- ' . number_format($mut->credit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2 text-right font-bold tabular-nums {{ $mut->current_balance < 0 ? 'text-red-400' : 'text-white' }}">
                                    Rp {{ number_format($mut->current_balance, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        @if(count($calcMutations) === 0)
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada mutasi pada periode ini. Ubah tanggal atau pilih "Semua".</td></tr>
                        @endif
                        <!-- Saldo awal periode (di dasar karena urutan terbaru di atas) -->
                        <tr style="background: rgba(30,41,59,0.3);">
                            <td class="px-4 py-2 text-gray-500">{{ $period['from'] ? \Carbon\Carbon::parse($period['from'])->format('d/m/Y') : '-' }}</td>
                            <td class="px-4 py-2 text-gray-500">-</td>
                            <td class="px-4 py-2 font-medium italic text-gray-400">{{ $period['from'] ? 'Saldo Awal Periode' : 'Saldo Awal (Opening Balance)' }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">-</td>
                            <td class="px-4 py-2 text-right text-gray-500">-</td>
                            <td class="px-4 py-2 text-right font-bold text-white tabular-nums">
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
            var card = document.getElementById('cbCard'), tg = document.getElementById('cbToggle');
            function setCollapsed(c) { card.classList.toggle('cb-collapsed', c); tg.textContent = c ? '▼ Tampilkan form' : '▲ Sembunyikan form'; try { localStorage.setItem('cbCollapsed', c ? '1' : '0'); } catch (e) {} }
            try { if (localStorage.getItem('cbCollapsed') === '1') setCollapsed(true); } catch (e) {}
            tg.addEventListener('click', function () { setCollapsed(!card.classList.contains('cb-collapsed')); });
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
