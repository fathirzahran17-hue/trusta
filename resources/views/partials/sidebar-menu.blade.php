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
        'cog'   => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];

    // item: [label, route, [active patterns], [excluded patterns]]
    $I = fn($l, $r, $p = null, $x = []) => ['l' => $l, 'r' => $r, 'p' => (array) ($p ?? $r), 'x' => $x];
    $H = fn($t) => ['head' => $t];
    $S = fn($n, $l, $r, $p = null) => ['l' => $l, 'r' => $r, 'p' => (array) ($p ?? $r), 'x' => [], 'n' => $n];

    $isStaff = auth()->user()->role === 'client_staff';

    $sections = [
        ['label' => 'Utama', 'items' => [
            ['single' => $I('Dashboard', 'client.dashboard'), 'icon' => 'home'],
        ]],
        ['label' => 'Transaksi', 'items' => [
            ['title' => 'Penjualan', 'icon' => 'sales', 'children' => [
                $I('Pusat Penjualan', 'client.sales.hub'),
                $H('Alur Penjualan'),
                $S(1, 'Pesanan Penjualan', 'client.sales.orders'),
                $S(2, 'Pengiriman Pesanan', 'client.sales.deliveries'),
                $S(3, 'Faktur Penjualan', 'client.sales-invoices.index', 'client.sales-invoices.*'),
                $S(4, 'Penerimaan Penjualan', 'client.sales.receipts'),
                $H('Pelunasan'),
                $I('Status Pelunasan', 'client.sales.settlement'),
                $H('Pelanggan & Produk Jual'),
                $I('Pelanggan', 'client.customers.index'),
                $I('Kategori Pelanggan', 'client.customers.categories'),
                $I('Barang Dijual', 'client.inventory.index', ['client.inventory.index', 'client.inventory.show', 'client.inventory.create']),
                $I('Jasa Dijual', 'client.inventory.services', ['client.inventory.services', 'client.inventory.services.create']),
                $I('Produk & Jasa (Semua)', 'client.inventory.all'),
                $H('Lainnya'),
                $I('Uang Muka Penjualan', 'client.sales.down_payments'),
                $I('Retur Penjualan', 'client.sales.returns'),
            ]],
            ['title' => 'Pembelian', 'icon' => 'buy', 'children' => [
                $I('Pusat Pembelian', 'client.purchases.hub'),
                $H('Alur Pembelian'),
                $S(1, 'Pesanan Pembelian', 'client.purchases.orders'),
                $S(2, 'Penerimaan Barang', 'client.purchases.receipts'),
                $S(3, 'Faktur Pembelian', 'client.purchase-invoices.index', 'client.purchase-invoices.*'),
                $S(4, 'Pembayaran Pembelian', 'client.purchases.payments'),
                $H('Pelunasan'),
                $I('Status Pelunasan', 'client.purchases.settlement'),
                $H('Pemasok & Barang Beli'),
                $I('Pemasok', 'client.suppliers.index'),
                $I('Kategori Pemasok', 'client.suppliers.categories'),
                $I('Barang Dibeli', 'client.inventory.index', ['client.inventory.index', 'client.inventory.show', 'client.inventory.create']),
                $H('Lainnya'),
                $I('Uang Muka Pembelian', 'client.purchases.down_payments'),
                $I('Retur Pembelian', 'client.purchases.returns'),
            ]],
            ['title' => 'Kas & Bank', 'icon' => 'cash', 'children' => [
                $I('Daftar Kas & Bank', 'client.cash-bank.index'),
                $I('Transfer Bank', 'client.cash-bank.transfers'),
                $I('Rekening Koran', 'client.cash-bank.statements'),
                $I('Rekonsiliasi Bank', 'client.cash-bank.reconciliation'),
            ]],
        ]],
        ['label' => 'Barang & Aset', 'items' => [
            ['title' => 'Persediaan', 'icon' => 'box', 'children' => [
                $H('Data Barang'),
                $I('Daftar Barang & Stok', 'client.inventory.index', ['client.inventory.index', 'client.inventory.show', 'client.inventory.create']),
                $I('Barang per Gudang', 'client.inventory.items_per_warehouse'),
                $I('Barang Stok Minimum', 'client.inventory.minimum_stock'),
                $H('Mutasi Stok'),
                $I('Permintaan Barang', 'client.inventory.requests'),
                $I('Pemindahan Barang', 'client.inventory.transfers'),
                $I('Penyesuaian Persediaan', 'client.inventory.adjustments'),
                $H('Stok Opname'),
                $I('Perintah Stok Opname', 'client.inventory.stock_opname_orders'),
                $I('Hasil Stok Opname', 'client.inventory.stock_opname_results'),
                $H('Data Master'),
                $I('Gudang', 'client.inventory.warehouses'),
                $I('Satuan Barang', 'client.inventory.units'),
                $I('Kategori Barang', 'client.inventory.categories', 'client.inventory.categories*'),
                $I('Merek Barang', 'client.inventory.brands'),
            ]],
            ['title' => 'Aset Tetap', 'icon' => 'asset', 'children' => [
                $I('Aset Tetap', 'client.fixed_assets.index'),
                $I('Aset per Lokasi', 'client.fixed_assets.locations'),
                $I('Pindah Aset', 'client.fixed_assets.transfers'),
                $I('Perubahan Aset Tetap', 'client.fixed_assets.modifications'),
                $I('Disposisi Aset Tetap', 'client.fixed_assets.dispositions'),
                $H('Data Master'),
                $I('Kategori Aset', 'client.fixed_assets.categories'),
                $I('Kategori Aset Tetap Pajak', 'client.fixed_assets.tax_categories'),
            ]],
        ]],
        ['label' => 'Akuntansi', 'items' => [
            ['title' => 'Buku Besar', 'icon' => 'book', 'children' => [
                $I('Akun Perkiraan', 'client.accounts.index', 'client.accounts.*', ['client.accounts.history', 'client.accounts.beginning_balance']),
                $I('Jurnal Umum', 'client.journals.index', 'client.journals.*'),
                $I('Histori Akun', 'client.accounts.history'),
                $I('Kartu Job', 'client.project-jobs.index', 'client.project-jobs.*'),
            ]],
        ]],
        ['label' => 'Laporan & Alat', 'items' => [
            ['title' => 'Laporan', 'icon' => 'chart', 'children' => [
                $I('Daftar Laporan', 'client.reports.index'),
                $I('SPT PPN / PPNBM', 'client.reports.spt_ppn'),
            ]],
            ['single' => $I('Kalkulator Pajak', 'client.reports.tax_calculator'), 'icon' => 'calc'],
        ]],
    ];

    if (!$isStaff) {
        $sections[] = ['label' => 'Pengaturan', 'items' => [
            ['title' => 'Perusahaan', 'icon' => 'cog', 'children' => [
                $I('Preferensi', 'client.settings.preferences'),
                $I('Pajak', 'client.taxes.index', 'client.taxes.*'),
                $I('Syarat Pembayaran', 'client.settings.payment_terms'),
                $I('Manajemen Staf', 'client.users.index', 'client.users.*'),
                $H('Periode & Saldo'),
                $I('Saldo Awal Akun', 'client.accounts.beginning_balance'),
                $I('Proses Akhir Bulan', 'client.company.month_end'),
                $I('Log Aktifitas', 'client.activity_logs.index', 'client.activity_logs.*'),
            ]],
        ]];
    }

    $isActive = fn($it) => request()->routeIs(...$it['p']) && ! ($it['x'] && request()->routeIs(...$it['x']));
    $hasActive = function ($children) use ($isActive) {
        foreach ($children as $c) { if (isset($c['r']) && $isActive($c)) return true; }
        return false;
    };
@endphp

@foreach($sections as $sec)
    <div class="nav-section-label">{{ $sec['label'] }}</div>
    @foreach($sec['items'] as $entry)
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
