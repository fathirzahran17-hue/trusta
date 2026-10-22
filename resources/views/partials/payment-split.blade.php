{{-- Split pelunasan ke akun potongan (mis. PPh dipotong). Param: $deductionAccounts, $sales (bool), $totalInput (selector input jumlah, opsional) --}}
@php $splitSales = (bool) $sales; @endphp
<div class="split-box" id="splitBox" data-total-input="{{ $totalInput ?? '' }}" data-sales="{{ $splitSales ? 1 : 0 }}" style="grid-column: 1 / -1;">
    <div class="split-head">
        <div>
            <div class="split-title">Potongan / split pelunasan <span>(opsional)</span></div>
            <div class="split-sub">
                {{ $splitSales
                    ? 'Dipakai bila pelanggan memotong sebagian (mis. PPh dipotong). Bagian ini tidak masuk kas, melainkan ke akun yang Anda pilih (mis. Pajak Dibayar Dimuka).'
                    : 'Dipakai bila Anda menahan sebagian untuk pajak (mis. Hutang PPh 23). Bagian ini tidak keluar dari kas, melainkan dicatat ke akun yang Anda pilih.' }}
            </div>
        </div>
        @if($deductionAccounts->isNotEmpty())
            <button type="button" class="split-add" id="splitAdd">+ Tambah potongan</button>
        @endif
    </div>

    @if($deductionAccounts->isEmpty())
        <div class="split-empty">Belum ada akun potongan. Centang <b>"Bisa dipakai untuk potongan / split pelunasan"</b> pada akun terkait (mis. {{ $splitSales ? 'Pajak Dibayar Dimuka' : 'Hutang PPh 23' }}) di <a href="{{ route('client.accounts.index') }}">Akun Perkiraan</a>.</div>
    @else
        <div id="splitRows"></div>
        <div class="split-sum" id="splitSum" hidden>
            <span>Dilunasi <b id="ss_total">Rp 0</b></span><span>&minus; Potongan <b id="ss_ded">Rp 0</b></span>
            <span>= {{ $splitSales ? 'Masuk kas/bank' : 'Keluar dari kas/bank' }} <b id="ss_cash">Rp 0</b></span>
        </div>
        <template id="splitTpl">
            <div class="split-row">
                <select data-f="account_id">
                    <option value="">— Pilih akun potongan —</option>
                    @foreach($deductionAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach
                </select>
                <input type="number" step="0.01" min="0" data-f="amount" placeholder="Jumlah (Rp)">
                <button type="button" class="split-del" aria-label="Hapus baris">&times;</button>
            </div>
        </template>
    @endif
</div>
<style>
    .split-box{border:1px dashed var(--border);border-radius:10px;padding:12px 14px;background:var(--surface-2, transparent)}
    .split-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .split-title{font-size:.8rem;font-weight:600;color:var(--text-primary, inherit)}
    .split-title span{font-weight:400;color:var(--text-dim)}
    .split-sub{font-size:.72rem;color:var(--text-dim);margin-top:2px;max-width:60ch}
    .split-add{font-size:.75rem;padding:5px 10px;border-radius:8px;border:1px solid var(--accent);color:var(--accent);background:var(--accent-soft);cursor:pointer}
    .split-row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr) 32px;gap:8px;margin-top:8px;align-items:center}
    .split-del{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--danger);font-size:1.1rem;cursor:pointer}
    .split-sum{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:10px;font-size:.78rem;color:var(--text-dim)}
    .split-sum b{color:var(--text-primary, inherit);font-variant-numeric:tabular-nums}
    .split-sum.bad b#ss_cash{color:var(--danger)}
    .split-empty{font-size:.75rem;color:var(--text-dim);margin-top:8px}
    .split-empty a{color:var(--accent);text-decoration:underline}
    @media (max-width:560px){.split-row{grid-template-columns:1fr 1fr 32px}.split-row select{grid-column:1 / -1}.split-row .split-del{grid-column:3}}
</style>
<script>
(function () {
    const box = document.getElementById('splitBox'); if (!box) return;
    const rows = document.getElementById('splitRows'), tpl = document.getElementById('splitTpl'), add = document.getElementById('splitAdd');
    if (!rows || !tpl) return;
    const form = box.closest('form');
    const sum = document.getElementById('splitSum');
    const fmt = n => 'Rp ' + Math.round(n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    let total = 0;

    function reindex() {
        [...rows.children].forEach((r, i) => r.querySelectorAll('[data-f]').forEach(el => el.name = 'deductions[' + i + '][' + el.dataset.f + ']'));
    }
    function dedTotal() { return [...rows.querySelectorAll('[data-f="amount"]')].reduce((s, e) => s + (parseFloat(e.value) || 0), 0); }
    function refresh() {
        const ded = dedTotal();
        sum.hidden = rows.children.length === 0;
        document.getElementById('ss_total').textContent = fmt(total);
        document.getElementById('ss_ded').textContent = fmt(ded);
        document.getElementById('ss_cash').textContent = fmt(total - ded);
        sum.classList.toggle('bad', ded > total + 0.001);
    }
    function addRow(accountId, amount) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        if (accountId) node.querySelector('select').value = accountId;
        if (amount) node.querySelector('input').value = amount;
        rows.appendChild(node); reindex(); refresh();
    }
    add.addEventListener('click', () => addRow());
    rows.addEventListener('input', refresh);
    rows.addEventListener('click', e => { if (e.target.closest('.split-del')) { e.target.closest('.split-row').remove(); reindex(); refresh(); } });

    // jumlah yang dilunasi: dari input tunggal (panel faktur) atau event dari halaman multi-faktur
    const sel = box.dataset.totalInput;
    if (sel) {
        const inp = document.querySelector(sel);
        if (inp) { const read = () => { total = parseFloat(inp.value) || 0; refresh(); }; inp.addEventListener('input', read); read(); }
    }
    if (form) form.addEventListener('paytotal', e => { total = e.detail.total || 0; refresh(); });

    if (form) form.addEventListener('submit', e => {
        if (dedTotal() > total + 0.001) { e.preventDefault(); alert('Total potongan melebihi jumlah yang dilunasi.'); }
    });

    @if(old('deductions'))
        @foreach(old('deductions') as $d)
            addRow(@json($d['account_id'] ?? ''), @json($d['amount'] ?? ''));
        @endforeach
    @endif
})();
</script>
