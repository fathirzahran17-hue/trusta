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

    // TRUSTA - Universal formatRupiah Helper
    window.formatRupiah = function(element, hiddenElement) {
        if (!element) return 0;
        var raw = (element.value !== undefined && element.value !== null) ? element.value.toString().trim() : '';

        // Nilai mentah dari database ("1500000.00" / "25000.50") hanya dikonversi saat dipanggil
        // di luar pengetikan. Saat mengetik, titik adalah pemisah ribuan ("1.500"), bukan desimal.
        var ev = window.event;
        var typing = ev && (ev.type === 'keyup' || ev.type === 'input' || ev.type === 'keydown');
        if (!typing && /^\d+\.(\d{1,2}|\d{4})$/.test(raw)) {
            var num = parseFloat(raw);
            if (!isNaN(num)) {
                raw = (num % 1 === 0) ? String(Math.round(num)) : raw.replace('.', ',');
            }
        }

        var clean = raw.replace(/[^,\d]/g, '');
        var split = clean.split(',');
        var sisa = split[0].length % 3;
        var rupiah = split[0].substr(0, sisa);
        var ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            var separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        if (split[1] !== undefined) {
            rupiah = rupiah + ',' + split[1].substring(0, 2);
        }

        element.value = rupiah;

        var realValue = 0;
        if (clean) {
            var numStr = split[0] + (split[1] !== undefined ? '.' + split[1].substring(0, 2) : '');
            realValue = parseFloat(numStr) || 0;
        }

        var targetHidden = hiddenElement;
        if (typeof hiddenElement === 'string') {
            targetHidden = document.getElementById(hiddenElement);
        }
        if (targetHidden) {
            targetHidden.value = realValue;
        }
        return realValue;
    };
</script>
