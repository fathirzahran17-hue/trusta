{{-- Terapkan tema sebelum halaman digambar agar tidak berkedip. Default: gelap. --}}
<script>
    (function () {
        var mode = 'dark';
        try {
            var saved = localStorage.getItem('trusta-theme');
            if (saved === 'light' || saved === 'dark') mode = saved;
        } catch (e) {}
        var root = document.documentElement;
        if (mode === 'dark') root.classList.add('dark'); else root.classList.remove('dark');
        root.dataset.theme = mode;
    })();
</script>
