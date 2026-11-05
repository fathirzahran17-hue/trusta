{{-- Peringatan saat bahan dipilih di form penjualan. Bahan tetap boleh dijual. --}}
<script>
    document.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('select[name="products[]"]')) return;
        const cell = e.target.closest('td');
        if (!cell) return;
        const option = e.target.options[e.target.selectedIndex];
        let note = cell.querySelector('.material-warn');
        if (option && option.dataset.warn) {
            if (!note) { note = document.createElement('div'); note.className = 'material-warn'; cell.appendChild(note); }
            note.textContent = 'Ini bahan, bukan barang jadi. Pastikan memang akan dijual.';
        } else if (note) {
            note.remove();
        }
    });
</script>
@if(auth()->user()->company?->warn_below_cost)
<script>
    // Harga jual di bawah harga pokok: hanya peringatan, transaksi tetap boleh disimpan
    (function () {
        function check(row) {
            var sel = row.querySelector('select[name="products[]"]'), option = sel && sel.options[sel.selectedIndex];
            var cost = option ? parseFloat(option.dataset.cost) || 0 : 0, price = parseFloat((row.querySelector('.input-price') || {}).value) || 0;
            var cell = row.querySelector('.col-price'), note = row.querySelector('.cost-warn');
            if (!cell) return;
            if (cost > 0 && price > 0 && price < cost) {
                if (!note) { note = document.createElement('div'); note.className = 'cost-warn'; cell.appendChild(note); }
                note.textContent = 'Di bawah harga pokok (Rp ' + cost.toLocaleString('id-ID') + ')';
            } else if (note) {
                note.remove();
            }
        }
        function fromEvent(e) { var row = e.target.closest && e.target.closest('.item-row'); if (row) check(row); }
        document.addEventListener('change', fromEvent);
        document.addEventListener('keyup', fromEvent);
        document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.item-row').forEach(check); });
    })();
</script>
@endif
