@php
    $sales = $kind === 'sales';
    $rp = fn ($n) => 'Rp ' . number_format($n, 2, ',', '.');
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
            <div class="pay-field">
                <label for="pf_cash">{{ $sales ? 'Nominal diterima di bank/kas' : 'Nominal dibayar dari bank/kas' }}</label>
                <input type="text" inputmode="decimal" autocomplete="off" id="pf_cash" class="money-in" placeholder="Isi sesuai mutasi bank, mis. 50.000.000,00">
                <div style="font-size:.68rem;color:var(--text-dim);margin-top:3px">Otomatis dibagi ke faktur terpilih (jatuh tempo terlama dulu). Bila kurang, sisanya tetap terbuka.</div>
            </div>
        </div>
        <div id="pf_info" class="pay-flow" style="margin: -4px 18px 12px; display:none;"></div>

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
                        <td class="r"><input type="text" inputmode="decimal" autocomplete="off" data-max="{{ $i['due'] }}" class="pay-amt pf-amt money-in" name="alloc[{{ $i['id'] }}]" disabled placeholder="0,00"></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="6" class="r">Sisa tagihan {{ strtolower($partyLabel) }} ini</td><td class="r">{{ $rp($p['due']) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        @endforeach

        <div class="pay-split-wrap" style="padding:0 16px 14px">
            @include('partials.payment-split', ['deductionAccounts' => $deductionAccounts, 'sales' => $sales, 'totalInput' => ''])
        </div>

        <div class="pay-foot">
            <div class="pay-total"><span id="pf_count">0 faktur dipilih</span> &middot; Total dilunasi (termasuk potongan)<b id="pf_total">Rp 0</b></div>
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
                            <td class="r">{{ $rp($h->amount) }}@if($h->deduction > 0)<div style="font-size:.68rem;color:var(--text-dim)">termasuk potongan {{ $rp($h->deduction) }}</div>@endif</td>
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
            const fmt = n => window.fmtRp(n);
            const cur = () => blocks.find(b => b.dataset.party === sel.value);

            function setRow(row, on, val) {
                const chk = row.querySelector('.pf-chk'), amt = row.querySelector('.pf-amt');
                chk.checked = on; amt.disabled = !on;
                amt.value = on ? window.fmtMoney(val !== undefined ? val : parseFloat(row.dataset.due)) : '';
                row.classList.toggle('picked', on);
            }
            const cashIn = f.querySelector('#pf_cash'), info = f.querySelector('#pf_info');
            let cashLocked = false; // true = pengguna mengetik nominal bank: alokasi faktur mengikuti nominal itu
            let userPicked = false; // true = pengguna sendiri yang mencentang faktur (bukan otomatis)
            const dedTotal = () => [...f.querySelectorAll('#splitRows [data-f="amount"]')].reduce((t, e) => t + window.parseMoney(e.value), 0);
            const round2 = n => Math.round(n * 100) / 100;

            // Bagi (nominal bank + potongan) ke faktur, mulai dari jatuh tempo terlama; sisanya tetap terbuka.
            function distribute() {
                const b = cur(); if (!b) return;
                const rows = [...b.querySelectorAll('.pf-row')];
                let left = round2(window.parseMoney(cashIn.value) + dedTotal());
                const anyChecked = userPicked && rows.some(r => r.querySelector('.pf-chk').checked);
                rows.forEach(r => {
                    const chk = r.querySelector('.pf-chk');
                    if (anyChecked && !chk.checked) return;           // hanya faktur yang dicentang
                    const due = parseFloat(r.dataset.due);
                    const take = round2(Math.min(due, Math.max(0, left)));
                    if (take > 0) { setRow(r, true, take); left = round2(left - take); }
                    else setRow(r, anyChecked, 0);
                });
                return left; // > 0 = kelebihan bayar
            }
            function recalc(over) {
                const b = cur(); let n = 0, total = 0, dueAll = 0, duePicked = 0;
                if (b) b.querySelectorAll('.pf-row').forEach(r => {
                    dueAll += parseFloat(r.dataset.due);
                    if (r.querySelector('.pf-chk').checked) { n++; duePicked += parseFloat(r.dataset.due); total += Math.min(window.parseMoney(r.querySelector('.pf-amt').value), parseFloat(r.dataset.due)); }
                    b.querySelector('.pf-all').checked = n === b.querySelectorAll('.pf-row').length;
                });
                total = round2(total);
                const ded = dedTotal(), cash = round2(total - ded);
                if (!cashLocked && document.activeElement !== cashIn) cashIn.value = total > 0 ? window.fmtMoney(cash) : '';
                f.querySelector('#pf_count').textContent = n + ' faktur dipilih';
                f.querySelector('#pf_total').textContent = fmt(total);
                f.dispatchEvent(new CustomEvent('paytotal', { detail: { total: total } }));
                const remain = round2(duePicked - total);
                let msg = '';
                if (total > 0) {
                    msg = '{{ $sales ? 'Diterima di bank' : 'Dibayar dari bank' }} <b>' + fmt(cash) + '</b>' + (ded > 0 ? ' + potongan <b>' + fmt(ded) + '</b>' : '') + ' = melunasi <b>' + fmt(total) + '</b>.';
                    msg += remain > 0.004 ? ' Sisa <b>' + fmt(remain) + '</b> tetap terbuka (belum lunas).' : ' Faktur terpilih lunas.';
                }
                if (over && over > 0.004) msg += ' <span style="color:var(--danger)">Kelebihan ' + fmt(over) + ' tidak dicatat di sini (melebihi sisa tagihan) — catat sebagai Uang Muka bila memang lebih bayar.</span>';
                info.innerHTML = msg; info.style.display = msg ? '' : 'none';
                const btn = f.querySelector('#pf_submit'); btn.disabled = !(total > 0);
                btn.style.opacity = total > 0 ? '' : '.5'; btn.style.cursor = total > 0 ? '' : 'not-allowed';
            }
            cashIn.addEventListener('input', () => { cashLocked = cashIn.value.trim() !== ''; recalc(distribute()); });
            function show() {
                blocks.forEach(b => {
                    const on = b === cur(); b.hidden = !on;
                    if (!on) b.querySelectorAll('.pf-row').forEach(r => setRow(r, false));
                });
                hint.hidden = !!cur(); recalc();
            }
            sel.addEventListener('change', () => { cashLocked = false; userPicked = false; cashIn.value = ''; show(); });
            f.addEventListener('change', e => {
                const t = e.target;
                if (t.classList.contains('pf-chk') || t.classList.contains('pf-all')) userPicked = true;
                if (t.classList.contains('pf-chk')) setRow(t.closest('.pf-row'), t.checked);
                if (t.classList.contains('pf-all')) cur().querySelectorAll('.pf-row').forEach(r => setRow(r, t.checked));
                if ((t.classList.contains('pf-chk') || t.classList.contains('pf-all')) && cashLocked) return recalc(distribute());
                recalc();
            });
            f.addEventListener('input', e => {
                if (e.target.closest && e.target.closest('#splitRows')) { return recalc(cashLocked ? distribute() : 0); } // potongan berubah
                if (!e.target.classList.contains('pf-amt')) return;
                cashLocked = false; // alokasi diubah manual: nominal bank mengikuti
                const max = parseFloat(e.target.dataset.max); if (window.parseMoney(e.target.value) > max) e.target.value = window.fmtMoney(max);
                recalc();
            });
            f.addEventListener('click', e => { if (e.target.closest && e.target.closest('.split-del')) setTimeout(() => recalc(cashLocked ? distribute() : 0), 0); });
            f.addEventListener('submit', e => { if (f.querySelector('#pf_submit').disabled) e.preventDefault(); });

            // pra-pilih dari tombol "Terima/Bayar" di daftar status pelunasan
            const preI = f.dataset.preInvoice, preP = f.dataset.preParty;
            let target = preI && f.querySelector('.pf-row[data-id="' + preI + '"]');
            if (target) sel.value = target.closest('.pf-block').dataset.party; else if (preP) sel.value = preP;
            show();
            if (target) { userPicked = true; setRow(target, true); recalc(); }
        })();
    </script>
</x-app-layout>
