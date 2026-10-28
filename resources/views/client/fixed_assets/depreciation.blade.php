@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 2, ',', '.');
    $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    [$yy, $mm] = explode('-', $month);
    $eligible = $rows->filter(fn ($r) => !$r->reason && $r->amount > 0);
@endphp
<x-app-layout>
    @section('title', 'Jalankan Penyusutan')
    @section('subtitle', 'Pilih bulan, aset, dan akun jurnal penyusutan')

    <div class="mb-4"><a href="{{ route('client.fixed_assets.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">&larr; Kembali ke Daftar Aset Tetap</a></div>

    @if(session('error'))<div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg">{{ $errors->first() }}</div>@endif

    <form method="GET" action="{{ route('client.fixed_assets.depreciation') }}" class="pay-card" id="monthForm" style="margin-bottom:14px">
        <div class="pay-card-head"><h3>Periode</h3><span class="sub">Ganti bulan untuk mengejar bulan yang terlewat atau menyusutkan bulan berjalan.</span></div>
        <div class="pay-form-grid">
            <div class="pay-field"><label for="month">Bulan penyusutan</label><input type="month" name="month" id="month" value="{{ $month }}" onchange="this.form.submit()"></div>
            <div class="pay-field" style="align-self:end;font-size:.78rem;color:var(--text-dim)">Jurnal bertanggal akhir bulan ({{ date('d', strtotime($date)) }} {{ $bulan[$mm] }} {{ $yy }}) kecuali Anda ubah di bawah.</div>
        </div>
    </form>

    <form method="POST" action="{{ route('client.fixed_assets.run_depreciation') }}" class="pay-card" id="depForm">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <div class="pay-card-head"><h3>Aset yang disusutkan &mdash; {{ $bulan[$mm] }} {{ $yy }}</h3>
            <span class="sub">Jurnal: Debit Akun Beban Penyusutan, Kredit Akun Akumulasi Penyusutan. Pilihan akun diingat untuk bulan berikutnya.</span></div>

        <div class="pay-form-grid">
            <div class="pay-field"><label for="date">Tanggal jurnal</label><input type="date" name="date" id="date" value="{{ old('date', $date) }}" min="{{ $month }}-01" max="{{ $date }}" required></div>
            <div class="pay-field"><label for="bulk_exp">Akun Beban untuk semua aset</label>
                <select id="bulk_exp"><option value="">— sesuai masing-masing aset —</option>
                    @foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
            <div class="pay-field"><label for="bulk_acc">Akun Akumulasi untuk semua aset</label>
                <select id="bulk_acc"><option value="">— sesuai masing-masing aset —</option>
                    @foreach($accumAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
        </div>
        @if($accumAccounts->filter(fn ($a) => stripos($a->name, 'akumulasi') !== false)->isEmpty())
            <div class="pay-empty" style="text-align:left">Belum ada akun <b>Akumulasi Penyusutan</b>. Buat dulu di <a href="{{ route('client.accounts.index') }}" style="color:var(--accent);text-decoration:underline">Akun Perkiraan</a> (tipe Aset, saldo normal Kredit), mis. "Akumulasi Penyusutan Peralatan Kantor".</div>
        @endif

        @if($rows->isEmpty())
            <div class="pay-empty">Belum ada aset tetap aktif.</div>
        @else
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr>
                    <th class="c" style="width:42px"><input type="checkbox" id="dep_all" aria-label="Pilih semua" {{ $eligible->isNotEmpty() ? 'checked' : 'disabled' }}></th>
                    <th>Aset</th><th class="r">Penyusutan bulan ini</th><th style="min-width:230px">Debit: Akun Beban</th><th style="min-width:230px">Kredit: Akun Akumulasi</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    @php
                        $a = $r->asset; $off = (bool) $r->reason || $r->amount <= 0;
                    @endphp
                    <tr class="dep-row {{ $off ? 'pay-void-row' : '' }}" data-amount="{{ $r->amount }}">
                        <td class="c"><input type="checkbox" class="dep-chk" name="assets[]" value="{{ $a->id }}" {{ $off ? 'disabled' : 'checked' }}></td>
                        <td class="wrap"><b>{{ $a->code }}</b> &middot; {{ $a->name }}
                            <div style="font-size:.7rem;color:var(--text-dim)">{{ optional($a->category)->name ?: 'Tanpa kategori' }} &bull; nilai buku {{ $rp($a->book_value) }}
                            @if($r->reason) &bull; <span style="color:var(--danger)">{{ $r->reason }}</span>@endif</div></td>
                        <td class="r">{{ $rp($r->amount) }}</td>
                        <td><select name="exp[{{ $a->id }}]" class="dep-exp" {{ $off ? 'disabled' : '' }}>
                            @foreach($expenseAccounts as $x)<option value="{{ $x->id }}" @selected(old("exp.{$a->id}", $r->exp) == $x->id)>{{ $x->code }} - {{ $x->name }}</option>@endforeach</select></td>
                        <td><select name="acc[{{ $a->id }}]" class="dep-acc" {{ $off ? 'disabled' : '' }}>
                            @foreach($accumAccounts as $x)<option value="{{ $x->id }}" @selected(old("acc.{$a->id}", $r->acc) == $x->id)>{{ $x->code }} - {{ $x->name }}</option>@endforeach</select></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="pay-foot">
            <div class="pay-total"><span id="dep_count">0 aset</span> &middot; Total penyusutan <b id="dep_total">Rp 0,00</b></div>
            <button type="submit" class="btn-primary" id="dep_submit" {{ $eligible->isEmpty() ? 'disabled style=opacity:.5;cursor:not-allowed' : '' }}>Jurnalkan Penyusutan</button>
        </div>
    </form>

    <div class="pay-card" style="margin-top: 18px;">
        <div style="padding: 10px 14px; font-weight: 700; font-size: .85rem; color: var(--text-strong); border-bottom: 1px solid var(--border);">
            Riwayat Penyusutan <span style="color: var(--text-dim); font-weight: 500;">&mdash; salah proses? Batalkan, jurnal balik dibuat otomatis dan akumulasi aset dikembalikan</span>
        </div>
        @if($history->isEmpty())
            <div style="padding: 18px 14px; font-size: .8rem; color: var(--text-dim);">Belum ada penyusutan yang bisa dibatalkan dari sini. (Penyusutan yang dijalankan sebelum fitur ini tidak tercatat per aset; koreksi dengan jurnal manual.)</div>
        @else
            <table class="pay-table">
                <thead><tr><th>Jurnal</th><th>Periode</th><th>Aset</th><th class="r">Jumlah</th><th class="c">Aksi</th></tr></thead>
                <tbody>
                @foreach($history as $h)
                    <tr>
                        <td class="num">@if($h->journal)<a href="{{ route('client.journals.show', $h->journal->id) }}">{{ $h->journal->ref_number }}</a>@endif</td>
                        <td>{{ \Carbon\Carbon::parse($h->period . '-01')->translatedFormat('F Y') }}</td>
                        <td class="wrap">{{ $h->assets }}</td>
                        <td class="r">Rp {{ number_format($h->total, 2, ',', '.') }}</td>
                        <td class="c">
                            <form method="POST" action="{{ route('client.fixed_assets.cancel_depreciation', $h->journal->id) }}" onsubmit="return confirm('Batalkan penyusutan ini?')">@csrf
                                <button type="submit" class="rpt-btn" style="color:#ef4444;">Batalkan</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        (function () {
            const f = document.getElementById('depForm'); if (!f) return;
            const rows = [...f.querySelectorAll('.dep-row')];
            function recalc() {
                let n = 0, t = 0;
                rows.forEach(r => { const c = r.querySelector('.dep-chk'); if (c && c.checked && !c.disabled) { n++; t += parseFloat(r.dataset.amount) || 0; } });
                f.querySelector('#dep_count').textContent = n + ' aset';
                f.querySelector('#dep_total').textContent = window.fmtRp(t);
                const b = f.querySelector('#dep_submit'); b.disabled = n === 0; b.style.opacity = n ? '' : '.5'; b.style.cursor = n ? '' : 'not-allowed';
            }
            f.addEventListener('change', e => {
                const t = e.target;
                if (t.id === 'dep_all') rows.forEach(r => { const c = r.querySelector('.dep-chk'); if (!c.disabled) c.checked = t.checked; });
                if (t.id === 'bulk_exp' && t.value) f.querySelectorAll('.dep-exp:not([disabled])').forEach(s => s.value = t.value);
                if (t.id === 'bulk_acc' && t.value) f.querySelectorAll('.dep-acc:not([disabled])').forEach(s => s.value = t.value);
                recalc();
            });
            f.addEventListener('submit', e => { if (f.querySelector('#dep_submit').disabled) e.preventDefault(); });
            recalc();
        })();
    </script>
</x-app-layout>
