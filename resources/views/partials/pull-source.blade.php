{{-- Tarik data dari dokumen sebelumnya: memilih dokumen memuat ulang form dengan pihak & barang terisi otomatis.
     $options: [['id'=>, 'text'=>, 'param'=>(opsional, default $param), 'group'=>(opsional)]]  $selected: "param=id" --}}
@php($__groups = collect($options)->groupBy(fn ($o) => $o['group'] ?? ''))
<div class="pull-bar">
    <div class="pull-ico">&#8629;</div>
    <div class="pull-body">
        <label for="pull_source">{{ $label }}</label>
        <select id="pull_source" onchange="window.location.href = this.value ? '{{ $baseUrl }}?' + this.value : '{{ $baseUrl }}'">
            <option value="">-- {{ $placeholder ?? 'Tanpa sumber (isi manual)' }} --</option>
            @foreach($__groups as $g => $items)
                @if($g !== '')<optgroup label="{{ $g }}">@endif
                @foreach($items as $o)
                    @php($__v = ($o['param'] ?? $param) . '=' . $o['id'])
                    <option value="{{ $__v }}" @selected(($selected ?? null) === $__v)>{{ $o['text'] }}</option>
                @endforeach
                @if($g !== '')</optgroup>@endif
            @endforeach
        </select>
        <small>Pilih untuk menarik pelanggan/pemasok dan barang dari dokumen tersebut — jumlah otomatis dibatasi sisa yang belum ditagih.</small>
        @if(!empty($emptyNote))<small style="color:var(--danger);font-weight:600">{{ $emptyNote }}</small>@endif
    </div>
</div>
