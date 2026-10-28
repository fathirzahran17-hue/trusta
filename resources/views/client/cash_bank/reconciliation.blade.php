<x-app-layout>
    @section('title', 'Rekonsiliasi Bank')
    @section('subtitle', 'Cocokkan mutasi kas/bank di buku dengan rekening koran dari bank')

    @php $rp = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

    <p class="pay-flow">
        Langkah: <b>1.</b> Pilih rekening & tanggal rekening koran → <b>2.</b> Isi saldo akhir di rekening koran → <b>3.</b> Centang mutasi yang tercantum di rekening koran → <b>4.</b> Simpan bila selisihnya 0.
        Biaya admin/bunga bank yang belum dibukukan, catat dulu di <a class="lnk" style="color:var(--accent)" href="{{ route('client.cash-bank.index') }}">Kas & Bank</a>.
    </p>

    <div class="pay-card">
        <form method="GET" action="{{ route('client.cash-bank.reconciliation') }}" class="pay-form-grid">
            <div class="pay-field" style="grid-column: span 2;">
                <label>Rekening Kas/Bank</label>
                <select name="account_id" onchange="this.form.submit()">
                    <option value="">-- Pilih Rekening --</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(optional($account)->id === $a->id)>{{ $a->code }} - {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pay-field">
                <label>Tanggal Rekening Koran</label>
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    @if($account)
        <form method="POST" action="{{ route('client.cash-bank.reconciliation.store') }}"
              x-data="{
                  base: {{ round($cleared, 2) }},
                  stmt: '{{ old('statement_balance') }}',
                  picked: {},
                  init() { document.querySelectorAll('.rec-cb').forEach(c => { this.picked[c.value] = c.checked ? parseFloat(c.dataset.amt) : 0; }); },
                  toggle(e) { this.picked[e.target.value] = e.target.checked ? parseFloat(e.target.dataset.amt) : 0; },
                  all(on) { document.querySelectorAll('.rec-cb').forEach(c => { c.checked = on; this.picked[c.value] = on ? parseFloat(c.dataset.amt) : 0; }); },
                  get clearedNow() { return Math.round((this.base + Object.values(this.picked).reduce((a, b) => a + b, 0)) * 100) / 100; },
                  get diff() { return Math.round(((window.parseMoney ? window.parseMoney(this.stmt) : parseFloat(this.stmt) || 0) - this.clearedNow) * 100) / 100; }
              }">
            @csrf
            <input type="hidden" name="account_id" value="{{ $account->id }}">
            <input type="hidden" name="date" value="{{ $date }}">

            <div class="rpt-summary">
                <div class="rpt-stat"><div class="l">Saldo di buku per {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</div><div class="v">{{ $rp($book) }}</div></div>
                <div class="rpt-stat"><div class="l">Sudah cocok sebelumnya</div><div class="v">{{ $rp($cleared) }}</div>
                    @if($last)<div style="font-size:.68rem; color:var(--text-dim)">rekonsiliasi terakhir {{ $last->statement_date->format('d/m/Y') }}</div>@endif</div>
                <div class="rpt-stat"><div class="l">Saldo yang dicentang</div><div class="v" x-text="window.fmtMoney(clearedNow)"></div></div>
                <div class="rpt-stat" :class="Math.abs(diff) < 0.005 ? 'good' : 'bad'"><div class="l">Selisih dengan rekening koran</div><div class="v" x-text="window.fmtMoney(diff)"></div></div>
            </div>

            <div class="pay-card">
                <div class="pay-card-head">
                    <h3>Mutasi Belum Dicocokkan ({{ $rows->count() }})</h3>
                    <span>
                        <button type="button" class="pay-mini" @click="all(true)">Centang semua</button>
                        <button type="button" class="pay-mini" @click="all(false)">Kosongkan</button>
                    </span>
                </div>
                <div class="pay-scroll">
                    <table class="pay-table">
                        <thead><tr><th class="c" style="width:36px;">✓</th><th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th class="r">Masuk</th><th class="r">Keluar</th></tr></thead>
                        <tbody>
                        @forelse($rows as $r)
                            @php $amt = round((float) $r->debit - (float) $r->credit, 2); @endphp
                            <tr>
                                <td class="c"><input type="checkbox" class="rec-cb" name="lines[]" value="{{ $r->id }}" data-amt="{{ $amt }}" @change="toggle($event)" @checked(in_array($r->id, old('lines', [])))></td>
                                <td>{{ \Carbon\Carbon::parse($r->transaction_date)->format('d/m/Y') }}</td>
                                <td><a class="lnk" href="{{ route('client.journals.show', $r->journal_id) }}">{{ $r->ref_number }}</a></td>
                                <td class="wrap">{{ $r->description ?: $r->jdesc }}</td>
                                <td class="r" style="color:#15803d">{{ (float) $r->debit > 0 ? $rp($r->debit) : '' }}</td>
                                <td class="r" style="color:var(--danger)">{{ (float) $r->credit > 0 ? $rp($r->credit) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="pay-empty">Semua mutasi sampai tanggal ini sudah dicocokkan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pay-form-grid" style="border-top:1px solid var(--border);">
                    <div class="pay-field">
                        <label>Saldo Akhir Rekening Koran *</label>
                        <input type="text" name="statement_balance" class="money-in" x-model="stmt" required placeholder="0,00">
                    </div>
                    <div class="pay-field" style="grid-column: span 2;">
                        <label>Catatan</label>
                        <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}" placeholder="mis. rekening koran Oktober 2026">
                    </div>
                </div>
                <div class="pay-foot">
                    <span class="pay-total" x-text="Math.abs(diff) < 0.005 ? 'Cocok — siap disimpan.' : 'Belum cocok: centang mutasi yang ada di rekening koran sampai selisihnya 0.'"></span>
                    <button type="submit" class="btn-primary" :disabled="Math.abs(diff) >= 0.005" :style="Math.abs(diff) >= 0.005 ? 'opacity:.5;cursor:not-allowed' : ''">Simpan Rekonsiliasi</button>
                </div>
            </div>
        </form>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Rekonsiliasi</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Tanggal Rekening Koran</th><th>Rekening</th><th class="r">Saldo Rekening Koran</th><th class="r">Mutasi Cocok</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                @forelse($history as $h)
                    <tr class="{{ $h->voided_at ? 'pay-void-row' : '' }}">
                        <td>{{ $h->statement_date->format('d/m/Y') }}</td>
                        <td>{{ optional($h->account)->name }}</td>
                        <td class="r">{{ $rp($h->statement_balance) }}</td>
                        <td class="r">{{ $h->cleared_count }}</td>
                        <td class="wrap">{{ $h->notes }}</td>
                        <td class="r">
                            @if($h->voided_at)
                                <span class="st st-void" style="text-decoration:none">Dibatalkan</span>
                            @elseif(in_array($h->id, $latestIds))
                                <form method="POST" action="{{ route('client.cash-bank.reconciliation.void', $h->id) }}" style="display:inline" onsubmit="return confirm('Batalkan rekonsiliasi ini? Mutasinya kembali belum dicocokkan (jurnal tidak berubah).')">@csrf
                                    <button class="pay-mini dng" type="submit">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="pay-empty">Belum ada rekonsiliasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
