{{-- Pencarian global: tombol di header + jendela pencarian (Ctrl+K / Cmd+K). Logika di resources/js/search.js --}}
<button type="button" class="header-icon-btn" data-search-open data-tooltip="Cari (Ctrl+K)" aria-label="Cari">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"></path></svg>
</button>
<div id="global-search" class="gs-overlay" hidden data-url="{{ route('client.search') }}">
    <div class="gs-box" role="dialog" aria-label="Pencarian">
        <input type="search" id="gs-input" class="gs-input" placeholder="Cari faktur, pesanan, pelanggan, produk, jurnal..." autocomplete="off">
        <div id="gs-results" class="gs-results"></div>
        <div class="gs-foot">Enter untuk membuka &bull; Esc untuk menutup</div>
    </div>
</div>
