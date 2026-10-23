{{-- Tarik banyak dokumen sumber sekaligus (satu pelanggan/pemasok). $options: [['id','param','group','text','party_id']] $checked: ["param=id",...] --}}
@php($__groups = collect($options)->groupBy('group'))
<details class="pull-bar pull-multi" id="pullMulti" @if(!empty($checked)) open @endif>
    <summary>
        <span class="pull-ico">&#8629;</span>
        <span class="pull-sum">
            <b>{{ $label }}</b>
            <small>Bisa memilih lebih dari satu dokumen dari {{ $partyWord }} yang sama — semuanya digabung dalam satu faktur.</small>
        </span>
        @if(!empty($sourceText))<span class="pull-chip">Sumber: {{ $sourceText }}</span>@endif
    </summary>
    <div class="pull-panel">
        @if(!empty($notice))<div class="pull-warn">{{ $notice }}</div>@endif
        <input type="search" id="pullFilter" placeholder="Cari nomor / nama {{ $partyWord }}…" class="pull-search">
        @forelse($__groups as $g => $items)
            <div class="pull-group">{{ $g }}</div>
            @foreach($items as $o)
                @php($__v = $o['param'] . '[]=' . $o['id'])
                <label class="pull-row" data-text="{{ strtolower($o['text']) }}">
                    <input type="checkbox" class="pull-cb" value="{{ $__v }}" data-party="{{ $o['party_id'] }}" @checked(in_array($o['param'] . '=' . $o['id'], $checked ?? []))>
                    <span>{{ $o['text'] }}</span>
                </label>
            @endforeach
        @empty
            <div class="pull-empty">Belum ada dokumen yang bisa ditarik.</div>
        @endforelse
        <div class="pull-actions">
            <span id="pullMsg" class="pull-msg"></span>
            <a href="{{ $baseUrl }}" class="pay-mini">Kosongkan</a>
            <button type="button" id="pullGo" class="pay-mini prim">Tarik yang dipilih</button>
        </div>
    </div>
</details>
<script>
(function () {
    var box = document.getElementById('pullMulti'); if (!box) return;
    var cbs = [].slice.call(box.querySelectorAll('.pull-cb')), msg = document.getElementById('pullMsg'), go = document.getElementById('pullGo');
    function check() {
        var parties = {}; cbs.forEach(function (c) { if (c.checked) parties[c.dataset.party] = 1; });
        var bad = Object.keys(parties).length > 1;
        msg.textContent = bad ? 'Pilih dokumen dari {{ $partyWord }} yang sama.' : '';
        go.disabled = bad || !cbs.some(function (c) { return c.checked; });
    }
    cbs.forEach(function (c) { c.addEventListener('change', check); }); check();
    go.addEventListener('click', function () {
        var q = cbs.filter(function (c) { return c.checked; }).map(function (c) { return c.value; }).join('&');
        window.location.href = '{{ $baseUrl }}' + (q ? '?' + q : '');
    });
    document.getElementById('pullFilter').addEventListener('input', function () {
        var t = this.value.toLowerCase();
        box.querySelectorAll('.pull-row').forEach(function (r) { r.style.display = r.dataset.text.indexOf(t) > -1 ? '' : 'none'; });
    });
})();
</script>
