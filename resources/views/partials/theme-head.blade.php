<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="alternate icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#0f766e">
{{-- Terapkan tema sebelum halaman digambar agar tidak berkedip. Default: gelap. --}}
<script>
    (function () {
        function apply() {
            var mode = 'dark';
            try {
                var saved = localStorage.getItem('trusta-theme');
                if (saved === 'light' || saved === 'dark') mode = saved;
            } catch (e) {}
            var root = document.documentElement;
            var changed = root.classList.contains('dark') !== (mode === 'dark');
            if (mode === 'dark') root.classList.add('dark'); else root.classList.remove('dark');
            root.dataset.theme = mode;
            if (changed) { try { window.dispatchEvent(new CustomEvent('trusta:theme', { detail: { mode: mode } })); } catch (e) {} }
        }
        apply();
        // Halaman yang dibuka lagi lewat tombol Kembali/Maju (cache browser) atau tab lain ikut tema terakhir.
        window.addEventListener('pageshow', apply);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) apply(); });
    })();

    // TRUSTA - format uang: selalu 2 digit di belakang koma (2.000.000,13)
    window.fmtMoney = function (n) {
        n = parseFloat(n); if (isNaN(n)) n = 0;
        return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    window.fmtRp = function (n) { return 'Rp ' + window.fmtMoney(n); };
    // "1.234.567,89" -> 1234567.89 ; angka biasa ("1234567.89") dipakai apa adanya
    window.parseMoney = function (v) {
        if (v === null || v === undefined) return 0;
        var s = String(v).trim(); if (!s) return 0;
        if (s.indexOf(',') !== -1 || /^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '').replace(',', '.');
        s = s.replace(/[^0-9.\-]/g, '');
        return parseFloat(s) || 0;
    };

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

        if (!typing && clean) {
            // di luar pengetikan (nilai awal/otomatis/selesai mengetik) selalu tampil 2 desimal
            var dec = (split[1] || '').substring(0, 2);
            while (dec.length < 2) dec += '0';
            rupiah = (rupiah || '0') + ',' + dec;
        } else if (split[1] !== undefined) {
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

    // Isi uang: rapikan ke 2 desimal saat selesai mengetik, blok teks saat fokus, dan pada nilai awal / baris baru
    (function () {
        var SEL = 'input[onkeyup*="formatRupiah"], input.money-in';
        function pad(el) {
            if (!el.value || !String(el.value).trim()) { return; }
            window.formatRupiah(el, el.classList.contains('money-in') ? null : el.previousElementSibling);
            if (!/,\d{2}$/.test(el.value)) { el.value = window.fmtMoney(window.parseMoney(el.value)); }
        }
        document.addEventListener('focusout', function (e) { if (e.target.matches && e.target.matches(SEL)) pad(e.target); });
        document.addEventListener('input', function (e) { if (e.target.matches && e.target.matches('input.money-in')) window.formatRupiah(e.target, null); }, true);
        document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches(SEL)) { try { e.target.select(); } catch (x) {} } });
        function sweep(root) {
            (root.querySelectorAll ? root.querySelectorAll(SEL) : []).forEach(function (el) {
                if (el.dataset.mpad) return; el.dataset.mpad = '1';
                if (el.value && String(el.value).trim() && !/,\d{2}$/.test(el.value)) { var h = el.classList.contains('money-in') ? null : el.previousElementSibling; var v = el.value; window.formatRupiah(el, h); if (!/,\d{2}$/.test(el.value)) el.value = window.fmtMoney(window.parseMoney(v)); }
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            sweep(document);
            new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches(SEL)) sweep({ querySelectorAll: function () { return [n]; } }); sweep(n); } }); }); }).observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
