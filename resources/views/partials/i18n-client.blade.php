{{-- Hanya saat bahasa Inggris: menerjemahkan teks yang dibuat JavaScript dan pesan confirm/alert memakai kamus yang sama. --}}
@if(app()->getLocale() === 'en')
<script>
(function () {
    var exact = null, lower = {}, patterns = [];
    function lookup(k) {
        if (exact[k] !== undefined) return exact[k];
        var m = k.match(/^([\s(\["'*•·–+=−←→▲▼>-]*)(.*?)([\s)\]"':;.,!?*…·–→-]*)$/);
        if (m && m[2] && (m[1] || m[3])) { var inner = lookup(m[2]); if (inner !== undefined) return m[1] + inner + m[3]; }
        var n = k.match(/^(\d[\d.,\-]*\s+(?:[-–—:]\s+)?)(\p{L}.*)$/u);
        if (n) { var rest = lookup(n[2]); if (rest !== undefined) return n[1] + rest; }
        var l = lower[k.toLowerCase()];
        if (l !== undefined) return k === k.toUpperCase() ? l.toUpperCase() : l;
        for (var i = 0; i < patterns.length; i++) {
            var p = k.match(patterns[i][0]);
            if (p) { var n = 1; return patterns[i][1].replace(/:x/g, function () { var v = p[n++] || ''; return exact[v] !== undefined ? exact[v] : v; }); }
        }
        return undefined;
    }
    function t(s) {
        if (!exact || typeof s !== 'string') return s;
        var c = s.trim();
        if (!c || !/\p{L}{2}/u.test(c)) return s;
        var v = lookup(c.replace(/\s+/g, ' '));
        return v === undefined ? s : s.replace(c, v);
    }
    window.trustaT = t;
    var nativeConfirm = window.confirm, nativeAlert = window.alert;
    window.confirm = function (m) { return nativeConfirm.call(window, t(String(m))); };
    window.alert = function (m) { return nativeAlert.call(window, t(String(m))); };
    var ATTRS = ['placeholder', 'title', 'aria-label', 'data-tooltip'];
    function element(el) {
        if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'TEXTAREA') return;
        ATTRS.forEach(function (a) { if (el.hasAttribute(a)) { var o = el.getAttribute(a), n = t(o); if (n !== o) el.setAttribute(a, n); } });
    }
    function walk(root) {
        if (root.nodeType === 3) { var n = t(root.nodeValue); if (n !== root.nodeValue) root.nodeValue = n; return; }
        if (root.nodeType !== 1 || root.tagName === 'SCRIPT' || root.tagName === 'STYLE' || root.tagName === 'TEXTAREA') return;
        element(root);
        for (var c = root.firstChild; c; c = c.nextSibling) walk(c);
    }
    function start() {
        walk(document.body);
        new MutationObserver(function (list) {
            list.forEach(function (m) {
                if (m.type === 'attributes') element(m.target);
                else if (m.type === 'characterData') walk(m.target);
                else m.addedNodes.forEach(walk);
            });
        }).observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ATTRS });
    }
    fetch('{{ route('locale.dictionary', ['v' => @filemtime(\App\Support\UiTranslator::dictionaryPath())]) }}', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        exact = {};
        Object.keys(d).forEach(function (k) {
            if (k.indexOf(':x') >= 0) {
                patterns.push([new RegExp('^' + k.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&').replace(/:x/g, '(.+?)') + '$'), d[k]]);
            } else { exact[k] = d[k]; lower[k.toLowerCase()] = d[k]; }
        });
        patterns.sort(function (a, b) { return b[0].source.length - a[0].source.length; });
        if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
    });
})();
</script>
@endif
