@php $isService = $kind === 'service'; $back = $isService ? route('client.inventory.services') : route('client.inventory.index'); @endphp
<x-app-layout>
    @section('title', 'Ubah ' . ($isService ? 'Jasa' : 'Barang'))
    @section('subtitle', $product->name)

    <div style="margin-bottom:14px"><a href="{{ $back }}" class="rpt-btn">&larr; Kembali ke daftar {{ $isService ? 'jasa' : 'barang' }}</a></div>

    <form action="{{ route('client.inventory.update', $product) }}" method="POST" style="max-width:980px">
        @csrf @method('PUT')
        @include('client.inventory._form')
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-bottom:30px">
            <a href="{{ $back }}" class="rpt-btn">Batal</a>
            <button type="submit" class="btn-primary" style="padding:9px 22px">Simpan Perubahan</button>
        </div>
    </form>
    <script>document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.searchable-select').forEach(function (el) { new TomSelect(el, {}); }); });</script>
</x-app-layout>
