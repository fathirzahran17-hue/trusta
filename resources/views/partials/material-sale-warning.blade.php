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
