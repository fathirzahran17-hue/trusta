@php
    $icons = [
        'home'  => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'sales' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'buy'   => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'cash'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'box'   => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'asset' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'book'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'chart' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z',
        'calc'  => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        'factory' => 'M3 21h18M5 21V10l5 3V10l5 3V5h4v16M9 17h1m4 0h1',
        'lock'  => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        'cog'   => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];

    // item: [label, route, [active patterns], [excluded patterns]]
    $I = fn($l, $r, $p = null, $x = [], $t = null) => ['l' => $l, 'r' => $r, 'p' => (array) ($p ?? $r), 'x' => $x, 't' => $t];
    $H = fn($t) => ['head' => $t];
    $S = fn($n, $l, $r, $p = null) => ['l' => $l, 'r' => $r, 'p' => (array) ($p ?? $r), 'x' => [], 'n' => $n];

    $isStaff = auth()->user()->role === 'client_staff';
    // Menu menyesuaikan bidang usaha (Preferensi > Bidang Usaha): Jasa tanpa stok, Manufaktur dengan Produksi.
    $bizType = \App\Support\BusinessType::current();
    $hasStock = \App\Support\BusinessType::hasStock($bizType);
    $steps = function (array $items) { $n = 0; return array_values(array_map(function ($c) use (&$n) { if (isset($c['n'])) { $c['n'] = ++$n; } return $c; }, array_filter($items))); };

    // Urutan mengikuti siklus akuntansi:
    // data awal -> transaksi harian (jual, beli, kas, persediaan, aset) -> jurnal & buku besar -> laporan -> tutup buku -> pengaturan.
    $sections = [
        ['label' => 'Utama', 'items' => [
            ['single' => $I('Dashboard', 'client.dashboard'), 'icon' => 'home'],
        ]],
        ['label' => '1. Data Awal', 'items' => [
            ['title' => 'Akun & Saldo Awal', 'icon' => 'book', 'children' => array_values(array_filter([
                $S(1, 'Akun Perkiraan (COA)', 'client.accounts.index', 'client.accounts.*', ['client.accounts.history', 'client.accounts.beginning_balance']),
                $isStaff ? null : $S(2, 'Saldo Awal Akun', 'client.accounts.beginning_balance'),
                $H('Pendukung'),
                $I('Pajak', 'client.taxes.index', 'client.taxes.*'),
                $isStaff ? null : $I('Syarat Pembayaran', 'client.settings.payment_terms'),
                $I('Kartu Job / Proyek', 'client.project-jobs.index', 'client.project-jobs.*'),
            ]))],
        ]],
        ['label' => '2. Transaksi', 'items' => [
            ['title' => 'Penjualan', 'icon' => 'sales', 'children' => $steps([
                $I('Ringkasan Penjualan', 'client.sales.hub'),
                $H('Alur Penjualan'),
                $S(1, 'Pelanggan', 'client.customers.index', ['client.customers.index','client.customers.create','client.customers.show','client.customers.edit']),
                $S(2, 'Pesanan Penjualan', 'client.sales.orders', 'client.sales.orders*'),
                $hasStock ? $S(3, 'Pengiriman Barang', 'client.sales.deliveries', 'client.sales.deliveries*') : null,
                $S(4, 'Faktur Penjualan', 'client.sales-invoices.index', ['client.sales-invoices.*', 'client.sales.settlement']),
                $S(5, 'Penerimaan Pembayaran', 'client.sales.receipts'),
                $H('Lainnya'),
                $I('Uang Muka Penjualan', 'client.sales.down_payments'),
                $I('Retur Penjualan', 'client.sales.returns', 'client.sales.returns*'),
                $I('Kategori Pelanggan', 'client.customers.categories'),
            ])],
            ['title' => 'Pembelian', 'icon' => 'buy', 'children' => $steps([
                $I('Ringkasan Pembelian', 'client.purchases.hub'),
                $H('Alur Pembelian'),
                $S(1, 'Pemasok', 'client.suppliers.index', ['client.suppliers.index','client.suppliers.create','client.suppliers.show','client.suppliers.edit']),
                $S(2, 'Pesanan Pembelian', 'client.purchases.orders', 'client.purchases.orders*'),
                $hasStock ? $S(3, 'Penerimaan Barang', 'client.purchases.receipts', 'client.purchases.receipts*') : null,
                $S(4, 'Faktur Pembelian', 'client.purchase-invoices.index', ['client.purchase-invoices.*', 'client.purchases.settlement']),
                $S(5, 'Pembayaran Pemasok', 'client.purchases.payments'),
                $H('Lainnya'),
                $I('Uang Muka Pembelian', 'client.purchases.down_payments'),
                $I('Retur Pembelian', 'client.purchases.returns', 'client.purchases.returns*'),
                $I('Kategori Pemasok', 'client.suppliers.categories'),
            ])],
            ['title' => 'Kas & Bank', 'icon' => 'cash', 'children' => [
                $I('Kas & Bank (Terima/Keluarkan)', 'client.cash-bank.index', ['client.cash-bank.index', 'client.cash-bank.show', 'client.cash-bank.receipts', 'client.cash-bank.payments']),
                $I('Transfer Antar Rekening', 'client.cash-bank.transfers'),
                $I('Rekonsiliasi Bank', 'client.cash-bank.reconciliation'),
            ]],
            $hasStock ? ['title' => 'Persediaan', 'icon' => 'box', 'children' => [
                $H('Produk & Jasa'),
                $I('Semua Produk & Jasa', 'client.inventory.all'),
                $I('Barang (bersetok)', 'client.inventory.index', ['client.inventory.index', 'client.inventory.show', 'client.inventory.edit', 'client.inventory.create'], [], 'goods'),
                $I('Jasa (tanpa stok)', 'client.inventory.services', ['client.inventory.services', 'client.inventory.services.create', 'client.inventory.show', 'client.inventory.edit'], [], 'service'),
                $H('Mutasi Stok (dijurnal)'),
                $I('Penyesuaian Persediaan', 'client.inventory.adjustments'),
                $I('Pemakaian Barang', 'client.inventory.requests'),
                $I('Stok Opname', 'client.inventory.stock_opname'),
                $I('Stok Menipis', 'client.inventory.minimum_stock'),
                $H('Data Pendukung'),
                $I('Gudang', 'client.inventory.warehouses'),
                $I('Satuan', 'client.inventory.units'),
                $I('Kategori Barang', 'client.inventory.categories', 'client.inventory.categories*'),
                $I('Merek', 'client.inventory.brands'),
            ]] : ['title' => 'Daftar Jasa', 'icon' => 'box', 'children' => [
                $I('Jasa', 'client.inventory.services', ['client.inventory.services', 'client.inventory.services.create', 'client.inventory.show', 'client.inventory.edit', 'client.inventory.all'], [], 'service'),
                $I('Kategori', 'client.inventory.categories', 'client.inventory.categories*'),
            ]],
            \App\Support\BusinessType::hasProduction($bizType) ? ['title' => 'Produksi', 'icon' => 'factory', 'children' => [
                $S(1, 'Resep Produk (BOM)', 'client.production.boms', 'client.production.boms*'),
                $S(2, 'Perintah Produksi', 'client.production.orders', 'client.production.orders*'),
            ]] : null,
            ['title' => 'Aset Tetap', 'icon' => 'asset', 'children' => [
                $S(1, 'Daftar Aset Tetap', 'client.fixed_assets.index', ['client.fixed_assets.index', 'client.fixed_assets.create', 'client.fixed_assets.edit']),
                $S(2, 'Penyusutan', 'client.fixed_assets.depreciation'),
                $S(3, 'Pelepasan / Penjualan Aset', 'client.fixed_assets.dispositions'),
                $H('Lainnya'),
                $I('Aset per Lokasi', 'client.fixed_assets.locations'),
                $I('Kategori Aset', 'client.fixed_assets.categories'),
            ]],
        ]],
        ['label' => '3. Jurnal & Buku Besar', 'items' => [
            ['title' => 'Akuntansi', 'icon' => 'book', 'children' => [
                $I('Jurnal Umum / Penyesuaian', 'client.journals.index', 'client.journals.*'),
                $I('Buku Besar per Akun', 'client.accounts.history'),
                $I('Cek Konsistensi Data', 'client.consistency'),
            ]],
        ]],
        ['label' => '4. Laporan', 'items' => [
            ['title' => 'Laporan', 'icon' => 'chart', 'children' => [
                $I('Laporan Keuangan & Lainnya', 'client.reports.index', ['client.reports.*'], ['client.reports.spt_ppn', 'client.reports.tax_calculator']),
                $H('Pajak'),
                $I('SPT PPN / PPNBM', 'client.reports.spt_ppn'),
                $I('Kalkulator Pajak', 'client.reports.tax_calculator'),
            ]],
        ]],
    ];

    if (!$isStaff) {
        $sections[] = ['label' => '5. Tutup Buku', 'items' => [
            ['single' => $I('Tutup Buku Bulanan', 'client.company.month_end'), 'icon' => 'lock'],
        ]];
        $sections[] = ['label' => 'Pengaturan', 'items' => [
            ['title' => 'Perusahaan', 'icon' => 'cog', 'children' => [
                $I('Preferensi', 'client.settings.preferences'),
                $I('Manajemen Staf', 'client.users.index', 'client.users.*'),
                $I('Log Aktivitas', 'client.activity_logs.index', 'client.activity_logs.*'),
            ]],
        ]];
    }

    // Halaman detail/edit produk dipakai bersama oleh Barang dan Jasa: sorot menu sesuai jenis produknya.
    $routeProduct = request()->route('product');
    $routeProductType = $routeProduct instanceof \App\Models\Product ? ($routeProduct->type === 'service' ? 'service' : 'goods') : null;
    $isActive = function ($it) use ($routeProductType) {
        if (! request()->routeIs(...$it['p']) || ($it['x'] && request()->routeIs(...$it['x']))) return false;
        if (! empty($it['t']) && request()->routeIs('client.inventory.show', 'client.inventory.edit')) return $routeProductType === $it['t'];
        return true;
    };
    $hasActive = function ($children) use ($isActive) {
        foreach ($children as $c) { if (isset($c['r']) && $isActive($c)) return true; }
        return false;
    };
@endphp

@foreach($sections as $sec)
    <div class="nav-section-label">{{ $sec['label'] }}</div>
    @foreach(array_filter($sec['items']) as $entry)
        @if(isset($entry['single']))
            @php $it = $entry['single']; @endphp
            <a href="{{ route($it['r']) }}" class="nav-item {{ $isActive($it) ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$entry['icon']] }}"></path></svg>
                {{ $it['l'] }}
            </a>
        @else
            <div x-data="{ open: {{ $hasActive($entry['children']) ? 'true' : 'false' }} }">
                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$entry['icon']] }}"></path></svg>
                    {{ $entry['title'] }}
                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <div class="nav-children" :class="{ 'show': open }">
                    @foreach($entry['children'] as $c)
                        @if(isset($c['head']))
                            <div class="nav-subhead">{{ $c['head'] }}</div>
                        @else
                            <a href="{{ route($c['r']) }}" class="nav-child {{ isset($c['n']) ? 'has-step' : '' }} {{ $isActive($c) ? 'active' : '' }}">
                                @isset($c['n'])<span class="nav-step">{{ $c['n'] }}</span>@endisset
                                {{ $c['l'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
@endforeach
