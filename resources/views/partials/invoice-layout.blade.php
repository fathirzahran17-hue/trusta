{{--
    Pilihan layout baris faktur (Barang, Jasa, Biaya). Param: $layouts [key => label], $layout (aktif), $hints [key => teks], $itemHeads [key => judul kolom].
    Daftar pilihan tiap layout ada di <template id="opts-{key}">. Baris yang sudah terisi dari layout lain tetap dipertahankan.
--}}
@if(count($layouts) > 1)
    <div class="layout-tabs" id="layoutTabs">
        @foreach($layouts as $key => $label)
            <button type="button" class="layout-tab" data-layout="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
@endif
@foreach($hints as $key => $hint)
    <p class="layout-hint" data-hint="{{ $key }}">{!! $hint !!}</p>
@endforeach

<script>
    window.InvoiceLayout = (function () {
        const heads = @js($itemHeads), keys = Object.keys(heads);
        const warnShortage = @js($warnShortage ?? false);
        let current = @js($layout), ready = false;

        const tpl = (k) => { const t = document.getElementById('opts-' + k); return t ? t.innerHTML : ''; };
        const placeholder = (k) => '<option value="" data-price="0" data-unit="">-- Pilih ' + heads[k] + ' --</option>';
        const layoutOf = (o) => (o.dataset.type === 'goods' ? 'barang' : (o.dataset.type === 'account' ? 'biaya' : 'jasa'));
        const rows = () => document.querySelectorAll('#itemsBody .item-row');

        function updateRow(tr) {
            const sel = tr.querySelector('.product-select'), cell = tr.querySelector('.row-stock');
            const o = sel && sel.options[sel.selectedIndex];
            if (!cell) return;
            if (o && o.dataset.type === 'goods') {
                const stock = parseFloat(o.dataset.stock) || 0, qty = parseFloat((tr.querySelector('.input-qty') || {}).value) || 0;
                cell.textContent = stock.toLocaleString('id-ID', { maximumFractionDigits: 4 }) + ' ' + (o.dataset.unit || '');
                cell.classList.toggle('is-low', warnShortage && qty > stock);
            } else {
                cell.textContent = '-';
                cell.classList.remove('is-low');
            }
        }

        function syncReceived() {
            const box = document.getElementById('receivedBox');
            if (!box) return;
            const direct = [...rows()].some((tr) => {
                const sel = tr.querySelector('.product-select'), o = sel && sel.options[sel.selectedIndex];
                return o && o.dataset.type === 'goods' && !tr.querySelector('.input-row-order').value && !tr.querySelector('.input-row-doc').value;
            });
            box.style.display = direct ? '' : 'none';
            box.querySelectorAll('input, select').forEach((el) => { el.disabled = !direct; });
            const check = box.querySelector('input[type="checkbox"]'), note = box.querySelector('.recv-off');
            if (note && check) note.style.display = check.checked ? 'none' : '';
        }

        function setLayout(k) {
            current = k;
            const table = document.getElementById('itemsTable');
            table.dataset.layout = k;
            document.querySelectorAll('.layout-tab').forEach((b) => b.classList.toggle('on', b.dataset.layout === k));
            document.querySelectorAll('.layout-hint').forEach((p) => { p.style.display = p.dataset.hint === k ? '' : 'none'; });
            const head = table.querySelector('.col-item');
            if (head) head.textContent = heads[k];
            rows().forEach((tr) => {
                const sel = tr.querySelector('.product-select'), val = sel.value, o = sel.options[sel.selectedIndex];
                const keep = val && o && layoutOf(o) !== k ? o.outerHTML : '';
                sel.innerHTML = placeholder(k) + tpl(k) + keep;
                sel.value = val;
                const unit = tr.querySelector('.row-unit');
                if (unit && !val) unit.textContent = k === 'biaya' ? '-' : 'pcs';
                updateRow(tr);
            });
            syncReceived();
        }

        return {
            // Sebelum siap semua pilihan tersedia, supaya baris tarikan dari dokumen asal bisa terisi.
            options() { return placeholder(current) + (ready ? tpl(current) : keys.map(tpl).join('')); },
            init() {
                rows().forEach((tr) => { tr.querySelector('.product-select').innerHTML = this.options(); });
                document.querySelectorAll('.layout-tab').forEach((b) => b.addEventListener('click', () => setLayout(b.dataset.layout)));
                const body = document.getElementById('itemsBody');
                const refresh = (e) => { const tr = e.target.closest('.item-row'); if (tr) updateRow(tr); syncReceived(); };
                body.addEventListener('change', refresh);
                body.addEventListener('input', refresh);
                const box = document.getElementById('receivedBox');
                if (box) box.addEventListener('change', syncReceived);
                setTimeout(() => {
                    ready = true;
                    const first = [...rows()].map((tr) => tr.querySelector('.product-select')).find((s) => s.value);
                    setLayout(first ? layoutOf(first.options[first.selectedIndex]) : current);
                }, 0);
            },
        };
    })();
</script>
