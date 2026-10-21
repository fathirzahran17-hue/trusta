{{-- Mengisi baris barang dari dokumen asal (SO/PO). Bekerja dengan form yang punya #btnAddRow, .item-row, select[name="products[]"], input[name="quantities[]"]. --}}
@if(!empty($rows))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var rows = @json($rows);
        var addBtn = document.getElementById('btnAddRow');
        if (!rows.length || !addBtn) return;
        for (var i = 1; i < rows.length; i++) addBtn.click();
        var trs = document.querySelectorAll('.item-row');
        rows.forEach(function (r, i) {
            var tr = trs[i];
            if (!tr) return;
            var sel = tr.querySelector('select[name="products[]"]');
            if (sel) {
                sel.value = String(r.product_id);
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            var qty = tr.querySelector('input[name="quantities[]"]');
            if (qty) { qty.value = r.quantity; qty.dispatchEvent(new Event('input', { bubbles: true })); }
            var ord = tr.querySelector('.input-row-order');
            if (ord && r.order_id) ord.value = String(r.order_id);
            if (r.order_no && sel) {
                var cell = sel.closest('td');
                if (cell && !cell.querySelector('.src-tag')) { var t = document.createElement('div'); t.className = 'src-tag'; t.textContent = 'dari ' + r.order_no; cell.appendChild(t); }
            }
            var desc = tr.querySelector('input[name="row_descriptions[]"]');
            if (desc && r.description) desc.value = r.description;
            var price = tr.querySelector('.input-price');
            if (price) {
                price.value = r.price;
                var disp = tr.querySelector('.input-price-display');
                if (disp) {
                    disp.value = r.price;
                    if (typeof window.formatRupiah === 'function') window.formatRupiah(disp, price);
                }
            }
            var disc = tr.querySelector('.input-discount');
            if (disc) { disc.value = r.discount; disc.dispatchEvent(new Event('input', { bubbles: true })); }
        });
        if (typeof window.calculateTotals === 'function') window.calculateTotals();
    });
</script>
@endif
