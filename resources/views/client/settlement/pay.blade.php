@php
    $sales = $kind === 'sales';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $partyLabel = $sales ? 'Pelanggan' : 'Pemasok';
    $storeUrl = $sales ? route('client.sales.receipts.store') : route('client.purchases.payments.store');
    $listUrl = $sales ? route('client.sales.settlement') : route('client.purchases.settlement');
@endphp
<x-app-layout>
    @section('title', $sales ? 'Penerimaan Penjualan' : 'Pembayaran Pembelian')
    @section('subtitle', $sales ? 'Terima pembayaran pelanggan dan lunasi faktur yang tertagih' : 'Bayar pemasok dan lunasi faktur hutang yang jatuh tempo')

    <div class="rpt-summary">
        <div class="rpt-stat"><div class="l">{{ $sales ? 'Piutang belum lunas' : 'Hutang belum lunas' }}</div><div class="v">{{ $rp($stats['open']) }}</div><div style="font-size:.7rem;color:var(--text-dim)">{{ $stats['openCount'] }} faktur</div></div>
        <div class="rpt-stat {{ $stats['overdue'] > 0 ? 'bad' : '' }}"><div class="l">Lewat jatuh tempo</div><div class="v">{{ $rp($stats['overdue']) }}</div><div style="font-size:.7rem;color:var(--text-dim)">{{ $stats['overdueCount'] }} faktur</div></div>
        <div class="rpt-stat good"><div class="l">{{ $sales ? 'Diterima' : 'Dibayar' }} bulan ini</div><div class="v">{{ $rp($stats['month']) }}</div></div>
    </div>

    <div class="rpt-actions" style="margin-bottom:14px">
        <a href="{{ $listUrl }}" class="rpt-btn">Status Pelunasan (lunas / belum)</a>
        <a href="{{ $sales ? route('client.cash-bank.receipts') : route('client.cash-bank.payments') }}" class="rpt-btn">{{ $sales ? 'Penerimaan lain (non-faktur)' : 'Pengeluaran lain (non-faktur)' }}</a>
    </div>

    <form method="POST" action="{{ $storeUrl }}" class="pay-card" id="payForm" data-pre-party="{{ (int) $preParty }}" data-pre-invoice="{{ (int) $preInvoice }}">
        @csrf
        <div class="pay-card-head">
            <h3>{{ $sales ? 'Terima Pembayaran' : 'Catat Pembayaran' }}</h3>
            <span class="sub">Pilih {{ strtolower($partyLabel) }}, centang faktur yang dibayar — sisa tagihan terisi otomatis dan bisa diubah untuk cicilan.</span>
        </div>

        <div class="pay-form-grid">
            <div class="pay-field">
                <label for="pf_party">{{ $partyLabel }}</label>
                <select name="party_id" id="pf_party" required>
                    <option value="">— Pilih {{ strtolower($partyLabel) }} —</option>
                    @foreach($parties as $p)
                        <option value="{{ $p['id'] }}">{{ $p['name'] }} · sisa {{ $rp($p['due']) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pay-field">
                <label for="pf_acc">{{ $sales ? 'Masuk ke akun' : 'Dibayar dari akun' }}</label>
                <select name="account_id" id="pf_acc" required>
                    @forelse($cashAccounts as $a)<option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->code }} - {{ $a->name }}</option>
                    @empty<option value="">(belum ada akun kas/bank)</option>@endforelse
                </select>
                @if($cashAccounts->isEmpty())<div style="font-size:.7rem;color:var(--danger);margin-top:4px">Tandai akun kas/bank di Bagan Akun (centang "Kas/Bank") terlebih dulu.</div>@endif
            </div>
            <div class="pay-field"><label for="pf_date">Tanggal</label><input type="date" name="payment_date" id="pf_date" value="{{ old('payment_date', date('Y-m-d')) }}" required></div>
            <div class="pay-field"><label for="pf_notes">Catatan</label><input type="text" name="notes" id="pf_notes" maxlength="255" value="{{ old('notes') }}" placeholder="Opsional, mis. transfer BCA"></div>
        </div>

        <div class="pay-empty" id="pf_hint">
            @if(count($parties))
                Pilih {{ strtolower($partyLabel) }} di atas untuk menampilkan faktur yang belum lunas.
            @else
                Tidak ada faktur yang belum lunas. Semua tagihan sudah beres.
            @endif
        </div>

        @foreach($parties as $p)
        <div class="pay-scroll pf-block" data-party="{{ $p['id'] }}" hidden>
            <table class="pay-table">
                <thead><tr>
                    <th class="c" style="width:42px"><input type="checkbox" class="pf-all" aria-label="Pilih semua"></th>
                    <th>No. Faktur</th><th>Tgl Faktur</th><th>Jatuh Tempo</th>
                    <th class="r">Total</th><th class="r">Sudah Dibayar</th><th class="r">Sisa Tagihan</th><th class="r">Dibayar Sekarang</th>
                </tr></thead>
                <tbody>
                @foreach($p['invoices'] as $i)
                    <tr class="pf-row" data-id="{{ $i['id'] }}" data-due="{{ $i['due'] }}">
                        <td class="c"><input type="checkbox" class="pf-chk" aria-label="Pilih {{ $i['number'] }}"></td>
                        <td class="num"><b>{{ $i['number'] }}</b>@if($i['ref'])<div style="font-size:.68rem;color:var(--text-dim)">Faktur pemasok: {{ $i['ref'] }}</div>@endif</td>
                        <td>{{ $i['date'] }}</td>
                        <td>{{ $i['due_date'] }} @if($i['overdue'])<span class="st st-late">Terlambat</span>@endif</td>
                        <td class="r">{{ $rp($i['total']) }}</td>
                        <td class="r">{{ $rp($i['paid']) }}</td>
                        <td class="r"><b>{{ $rp($i['due']) }}</b></td>
                        <td class="r"><input type="number" step="0.01" min="0" max="{{ $i['due'] }}" class="pay-amt pf-amt" name="alloc[{{ $i['id'] }}]" disabled placeholder="0"></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="6" class="r">Sisa tagihan {{ strtolower($partyLabel) }} ini</td><td class="r">{{ $rp($p['due']) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        @endforeach

        <div class="pay-foot">
            <div class="pay-total"><span id="pf_count">0 faktur dipilih</span> &middot; Total {{ $sales ? 'diterima' : 'dibayar' }}<b id="pf_total">Rp 0</b></div>
            <button type="submit" class="btn-primary" id="pf_submit" disabled style="opacity:.5;cursor:not-allowed">{{ $sales ? 'Terima Pembayaran' : 'Catat Pembayaran' }}</button>
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat {{ $sales ? 'Penerimaan' : 'Pembayaran' }}</h3><span class="sub">Salah catat? Batalkan — jurnal balik dibuat otomatis dan faktur kembali ke status sebelumnya.</span></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Tanggal</th><th>No.</th><th>{{ $partyLabel }}</th><th>Faktur</th><th>Akun Kas/Bank</th><th class="r">Jumlah</th><th class="c">Aksi</th></tr></thead>
                <tbody>
                    @forelse($history as $h)
                        <tr class="{{ $h->voided ? 'pay-void-row' : '' }}">
                            <td>{{ optional($h->date)->format('d M Y') }}</td>
                            <td class="num">@if($h->journal_id)<a href="{{ route('client.journals.show', $h->journal_id) }}">{{ $h->number }}</a>@else{{ $h->number }}@endif</td>
                            <td class="wrap">{{ $h->party ?: '-' }}</td>
                            <td class="wrap">{{ $h->invoices }}</td>
                            <td>{{ $h->account }}</td>
                            <td class="r">{{ $rp($h->amount) }}</td>
                            <td class="c">
                                @if($h->voided)
                                    <span class="st st-void" style="text-decoration:none">Dibatalkan</span>
                                @else
                                    <form method="POST" action="{{ route('client.invoice-payments.void', $h->first) }}" onsubmit="return confirm('Batalkan {{ $sales ? 'penerimaan' : 'pembayaran' }} {{ $h->number }}? Jurnal balik dibuat dan faktur terkait kembali belum lunas sesuai jumlahnya.');" style="display:inline">
                                        @csrf <button type="submit" class="pay-mini dng">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pay-empty">Belum ada {{ $sales ? 'penerimaan' : 'pembayaran' }} faktur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const f = document.getElementById('payForm'); if (!f) return;
            const sel = f.querySelector('#pf_party'), hint = f.querySelector('#pf_hint');
            const blocks = [...f.querySelectorAll('.pf-block')];
            const fmt = n => 'Rp ' + Math.round(n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const cur = () => blocks.find(b => b.dataset.party === sel.value);

            function setRow(row, on, val) {
                const chk = row.querySelector('.pf-chk'), amt = row.querySelector('.pf-amt');
                chk.checked = on; amt.disabled = !on;
                amt.value = on ? (val !== undefined ? val : row.dataset.due) : '';
                row.classList.toggle('picked', on);
            }
            function recalc() {
                const b = cur(); let n = 0, total = 0;
                if (b) b.querySelectorAll('.pf-row').forEach(r => {
                    if (r.querySelector('.pf-chk').checked) { n++; total += Math.min(parseFloat(r.querySelector('.pf-amt').value) || 0, parseFloat(r.dataset.due)); }
                    b.querySelector('.pf-all').checked = n === b.querySelectorAll('.pf-row').length;
                });
                f.querySelector('#pf_count').textContent = n + ' faktur dipilih';
                f.querySelector('#pf_total').textContent = fmt(total);
                const btn = f.querySelector('#pf_submit'); btn.disabled = !(total > 0);
                btn.style.opacity = total > 0 ? '' : '.5'; btn.style.cursor = total > 0 ? '' : 'not-allowed';
            }
            function show() {
                blocks.forEach(b => {
                    const on = b === cur(); b.hidden = !on;
                    if (!on) b.querySelectorAll('.pf-row').forEach(r => setRow(r, false));
                });
                hint.hidden = !!cur(); recalc();
            }
            sel.addEventListener('change', show);
            f.addEventListener('change', e => {
                const t = e.target;
                if (t.classList.contains('pf-chk')) setRow(t.closest('.pf-row'), t.checked);
                if (t.classList.contains('pf-all')) cur().querySelectorAll('.pf-row').forEach(r => setRow(r, t.checked));
                recalc();
            });
            f.addEventListener('input', e => {
                if (!e.target.classList.contains('pf-amt')) return;
                const max = parseFloat(e.target.max); if (parseFloat(e.target.value) > max) e.target.value = max;
                recalc();
            });
            f.addEventListener('submit', e => { if (f.querySelector('#pf_submit').disabled) e.preventDefault(); });

            // pra-pilih dari tombol "Terima/Bayar" di daftar status pelunasan
            const preI = f.dataset.preInvoice, preP = f.dataset.preParty;
            let target = preI && f.querySelector('.pf-row[data-id="' + preI + '"]');
            if (target) sel.value = target.closest('.pf-block').dataset.party; else if (preP) sel.value = preP;
            show();
            if (target) setRow(target, true), recalc();
        })();
    </script>
</x-app-layout>
