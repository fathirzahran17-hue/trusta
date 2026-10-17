<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="alternate icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#0f766e">
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
