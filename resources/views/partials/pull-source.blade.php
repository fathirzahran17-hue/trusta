{{-- Tarik data dari dokumen sebelumnya: memilih dokumen memuat ulang form dengan baris & pihak terisi otomatis. --}}
@php($__sel = $selected ?? null)
<div class="pull-bar">
    <div class="pull-ico">&#8629;</div>
    <div class="pull-body">
        <label for="pull_source">{{ $label }}</label>
        <select id="pull_source" onchange="window.location.href = this.value ? '{{ $baseUrl }}?{{ $param }}=' + this.value : '{{ $baseUrl }}'">
            <option value="">-- {{ $placeholder ?? 'Tanpa sumber (isi manual)' }} --</option>
            @foreach($options as $o)
                <option value="{{ $o['id'] }}" @selected((int) $__sel === (int) $o['id'])>{{ $o['text'] }}</option>
            @endforeach
        </select>
        <small>Pilih untuk menarik pelanggan/pemasok dan barang dari dokumen tersebut — jumlah otomatis dibatasi sisa yang belum diproses.</small>
    </div>
</div>
