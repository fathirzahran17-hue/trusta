{{-- Mengisi baris barang dari dokumen asal (SO/PO). Bekerja dengan form yang punya #btnAddRow, .item-row, select[name="products[]"], input[name="quantities[]"]. --}}
@php
    // Validasi gagal: isi baris yang sudah diketik dikembalikan, bukan hilang.
    $oldProducts = old('products');
    if (is_array($oldProducts) && count($oldProducts)) {
        $rows = [];
        foreach (array_values($oldProducts) as $i => $productId) {
            $rows[] = ['product_id' => $productId ?? '', 'quantity' => old("quantities.$i", 1), 'price' => old("prices.$i", 0), 'discount' => old("discounts.$i", 0),
                'description' => old("row_descriptions.$i"), 'order_id' => old("row_orders.$i"), 'doc_id' => old("row_docs.$i"), 'taxable' => old("taxable.$i")];
        }
    }
@endphp
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
            var doc = tr.querySelector('.input-row-doc');
            if (doc && r.doc_id) doc.value = String(r.doc_id);
            if (r.order_no && sel) {
                var cell = sel.closest('td');
                if (cell && !cell.querySelector('.src-tag')) { var t = document.createElement('div'); t.className = 'src-tag'; t.textContent = 'dari ' + r.order_no; cell.appendChild(t); }
            }
            var desc = tr.querySelector('input[name="row_descriptions[]"]');
            if (desc && r.description) desc.value = r.description;
            var price = tr.querySelector('.input-price');
            if (price && r.price !== undefined && r.price !== null) {
                price.value = r.price;
                var disp = tr.querySelector('.input-price-display');
                if (disp) {
                    disp.value = String(r.price).replace('.', ',');
                    if (typeof window.formatRupiah === 'function') window.formatRupiah(disp, price);
                }
            }
            var disc = tr.querySelector('.input-discount');
            if (disc && r.discount !== undefined && r.discount !== null) { disc.value = r.discount; disc.dispatchEvent(new Event('input', { bubbles: true })); }
            if (r.taxable !== undefined && r.taxable !== null && window.LineTax) window.LineTax.set(tr, r.taxable);
        });
        if (typeof window.calculateTotals === 'function') window.calculateTotals();
    });
</script>
@endif
