@php($__none = isset($products) && count($products) === 0)
@if($__none)
    <div style="margin-bottom:12px; padding:10px 14px; border-radius:10px; border:1px dashed var(--border); background:var(--surface-2, rgba(20,184,166,.08)); font-size:.8rem; line-height:1.5;">
        <strong>Belum ada produk atau jasa.</strong>
        Daftar di sini diambil dari data produk Anda. Tambahkan dulu:
        <a href="{{ route('client.inventory.create') }}" style="color:var(--accent); font-weight:600;">+ Barang</a>
        &nbsp;·&nbsp;
        <a href="{{ route('client.inventory.services.create') }}" style="color:var(--accent); font-weight:600;">+ Jasa</a>
        <span style="opacity:.7">(menu Penjualan &rsaquo; Barang Dijual / Jasa Dijual)</span>
    </div>
@endif
