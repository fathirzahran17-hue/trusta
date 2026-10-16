<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TRUSTA') }} - @yield('title', 'Trusted Accounting Solution')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Tema terang/gelap (diterapkan sebelum render agar tidak berkedip) -->
        @include('partials.theme-head')

        <!-- Scripts (Alpine.js sudah dibundel di app.js) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- TomSelect for searchable dropdowns -->
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: true }" x-init="
        if (window.innerWidth < 768) sidebarOpen = false;
        window.addEventListener('resize', () => {
            if (window.innerWidth < 768) sidebarOpen = false;
        });
    ">

        <div class="app-layout">
            <!-- Sidebar Overlay (Mobile) -->
            <div class="sidebar-overlay" :class="{ 'show': sidebarOpen }" @click="sidebarOpen = false"></div>

            <!-- Sidebar Navigation -->
            <aside class="sidebar" :class="{ 'collapsed': !sidebarOpen }">
                <div class="sidebar-inner">
                    <!-- Logo -->
                    <div class="sidebar-logo">
                        @if(auth()->user()->isSuperAdmin())
                            <a href="{{ route('superadmin.dashboard') }}">
                        @else
                            <a href="{{ route('client.dashboard') }}">
                        @endif
                            <div class="logo-text">TRUST<span>A</span></div>
                            <div class="logo-sub">Trusted Accounting Solution</div>
                        </a>
                    </div>

                    <!-- Navigation -->
                    <nav class="sidebar-nav">
                        @if(auth()->user()->isSuperAdmin())
                            {{-- ===== Super Admin Navigation ===== --}}
                            <div class="nav-section-label">Super Admin</div>
                            <a href="{{ route('superadmin.dashboard') }}" class="nav-item {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                Dashboard
                            </a>
                            <a href="{{ route('superadmin.clients.index') }}" class="nav-item {{ request()->routeIs('superadmin.clients.*') ? 'active' : '' }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                Kelola Klien
                            </a>
                            <a href="{{ route('superadmin.default-accounts.index') }}" class="nav-item {{ request()->routeIs('superadmin.default-accounts.*') ? 'active' : '' }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Template CoA
                            </a>
                        @else
                            {{-- ===== Client Navigation ===== --}}

                            {{-- Menu Utama (Dashboard) --}}
                            <div class="nav-section-label">Menu Utama</div>
                            <a href="{{ route('client.dashboard') }}" class="nav-item {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                Dashboard
                            </a>

                            @if(auth()->user()->role !== 'client_staff')
                            {{-- Pengaturan & Perusahaan (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.settings.*') || request()->routeIs('client.company.*') || request()->routeIs('client.users.*') || request()->routeIs('client.activity_logs.*') || request()->routeIs('client.taxes.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Pengaturan & Perusahaan
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.settings.preferences') }}" class="nav-child dot-orange {{ request()->routeIs('client.settings.preferences') ? 'active' : '' }}">Preferensi</a>
                                    <a href="{{ route('client.taxes.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.taxes.*') ? 'active' : '' }}">Pajak</a>
                                    <a href="{{ route('client.settings.payment_terms') }}" class="nav-child dot-blue {{ request()->routeIs('client.settings.payment_terms') ? 'active' : '' }}">Syarat Pembayaran</a>
                                    <a href="{{ route('client.users.index') }}" class="nav-child dot-green {{ request()->routeIs('client.users.*') ? 'active' : '' }}">Manajemen Staf</a>
                                    <a href="{{ route('client.company.month_end') }}" class="nav-child dot-green {{ request()->routeIs('client.company.month_end') ? 'active' : '' }}">Proses Akhir Bulan</a>
                                    <a href="{{ route('client.accounts.beginning_balance') }}" class="nav-child dot-orange {{ request()->routeIs('client.accounts.beginning_balance') ? 'active' : '' }}">Saldo Awal Akun</a>
                                    <a href="{{ route('client.activity_logs.index') }}" class="nav-child dot-purple {{ request()->routeIs('client.activity_logs.*') ? 'active' : '' }}">Log Aktifitas</a>
                                </div>
                            </div>
                            @endif



                            {{-- Buku Besar (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.accounts.*') || request()->routeIs('client.journals.*') || request()->routeIs('client.project-jobs.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                    Buku Besar
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.accounts.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.accounts.*') && !request()->routeIs('client.accounts.history') && !request()->routeIs('client.accounts.beginning_balance') ? 'active' : '' }}">Akun Perkiraan</a>
                                    <a href="{{ route('client.journals.index') }}" class="nav-child dot-green {{ request()->routeIs('client.journals.*') ? 'active' : '' }}">Jurnal Umum</a>
                                    <a href="{{ route('client.project-jobs.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.project-jobs.*') ? 'active' : '' }}">Kartu Job</a>
                                    <a href="{{ route('client.accounts.history') }}" class="nav-child dot-purple {{ request()->routeIs('client.accounts.history') ? 'active' : '' }}">Histori Akun</a>
                                </div>
                            </div>

                            {{-- Kas & Bank (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.cash-bank.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    Kas & Bank
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.cash-bank.index') }}" class="nav-child dot-green {{ request()->routeIs('client.cash-bank.index') ? 'active' : '' }}">Daftar Kas & Bank</a>
                                    <a href="{{ route('client.cash-bank.transfers') }}" class="nav-child dot-green {{ request()->routeIs('client.cash-bank.transfers') ? 'active' : '' }}">Transfer Bank</a>
                                    <a href="{{ route('client.cash-bank.statements') }}" class="nav-child dot-purple {{ request()->routeIs('client.cash-bank.statements') ? 'active' : '' }}">Rekening Koran</a>

                                    <a href="{{ route('client.cash-bank.reconciliation') }}" class="nav-child dot-purple {{ request()->routeIs('client.cash-bank.reconciliation') ? 'active' : '' }}">Rekonsiliasi Bank</a>
                                </div>
                            </div>

                            {{-- Penjualan (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.sales-invoices.*') || request()->routeIs('client.customers.*') || request()->routeIs('client.sales.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Penjualan
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.sales.orders') }}" class="nav-child dot-green {{ request()->routeIs('client.sales.orders') ? 'active' : '' }}">Pesanan Penjualan</a>
                                    <a href="{{ route('client.sales.deliveries') }}" class="nav-child dot-green {{ request()->routeIs('client.sales.deliveries') ? 'active' : '' }}">Pengiriman Pesanan</a>
                                    <a href="{{ route('client.sales.down_payments') }}" class="nav-child dot-green {{ request()->routeIs('client.sales.down_payments') ? 'active' : '' }}">Uang Muka Penjualan</a>
                                    <a href="{{ route('client.sales-invoices.index') }}" class="nav-child dot-green {{ request()->routeIs('client.sales-invoices.*') ? 'active' : '' }}">Faktur Penjualan</a>
                                    <a href="{{ route('client.sales.receipts') }}" class="nav-child dot-green {{ request()->routeIs('client.sales.receipts') ? 'active' : '' }}">Penerimaan Penjualan</a>
                                    <a href="{{ route('client.sales.returns') }}" class="nav-child dot-green {{ request()->routeIs('client.sales.returns') ? 'active' : '' }}">Retur Penjualan</a>
                                    <a href="{{ route('client.customers.categories') }}" class="nav-child dot-blue {{ request()->routeIs('client.customers.categories') ? 'active' : '' }}">Kategori Pelanggan</a>
                                    <a href="{{ route('client.customers.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.customers.index') ? 'active' : '' }}">Pelanggan</a>
                                </div>
                            </div>

                            {{-- Pembelian (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.purchase-invoices.*') || request()->routeIs('client.suppliers.*') || request()->routeIs('client.purchases.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Pembelian
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.purchases.orders') }}" class="nav-child dot-green {{ request()->routeIs('client.purchases.orders') ? 'active' : '' }}">Pesanan Pembelian</a>
                                    <a href="{{ route('client.purchases.receipts') }}" class="nav-child dot-green {{ request()->routeIs('client.purchases.receipts') ? 'active' : '' }}">Penerimaan Barang</a>
                                    <a href="{{ route('client.purchases.down_payments') }}" class="nav-child dot-green {{ request()->routeIs('client.purchases.down_payments') ? 'active' : '' }}">Uang Muka Pembelian</a>
                                    <a href="{{ route('client.purchase-invoices.index') }}" class="nav-child dot-green {{ request()->routeIs('client.purchase-invoices.*') ? 'active' : '' }}">Faktur Pembelian</a>
                                    <a href="{{ route('client.purchases.payments') }}" class="nav-child dot-green {{ request()->routeIs('client.purchases.payments') ? 'active' : '' }}">Pembayaran Pembelian</a>
                                    <a href="{{ route('client.purchases.returns') }}" class="nav-child dot-green {{ request()->routeIs('client.purchases.returns') ? 'active' : '' }}">Retur Pembelian</a>
                                    <a href="{{ route('client.suppliers.categories') }}" class="nav-child dot-blue {{ request()->routeIs('client.suppliers.categories') ? 'active' : '' }}">Kategori Pemasok</a>
                                    <a href="{{ route('client.suppliers.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.suppliers.index') ? 'active' : '' }}">Pemasok</a>
                                </div>
                            </div>

                            {{-- Persediaan (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.inventory.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    Persediaan
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.inventory.requests') }}" class="nav-child dot-green {{ request()->routeIs('client.inventory.requests') ? 'active' : '' }}">Permintaan Barang</a>
                                    <a href="{{ route('client.inventory.transfers') }}" class="nav-child dot-green {{ request()->routeIs('client.inventory.transfers') ? 'active' : '' }}">Pemindahan Barang</a>
                                    <a href="{{ route('client.inventory.adjustments') }}" class="nav-child dot-green {{ request()->routeIs('client.inventory.adjustments') ? 'active' : '' }}">Penyesuaian Persediaan</a>
                                    <a href="{{ route('client.inventory.stock_opname_orders') }}" class="nav-child dot-green {{ request()->routeIs('client.inventory.stock_opname_orders') ? 'active' : '' }}">Perintah Stok Opname</a>
                                    <a href="{{ route('client.inventory.stock_opname_results') }}" class="nav-child dot-green {{ request()->routeIs('client.inventory.stock_opname_results') ? 'active' : '' }}">Hasil Stok Opname</a>
                                    <a href="{{ route('client.inventory.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.inventory.index') || request()->routeIs('client.inventory.show') || request()->routeIs('client.inventory.edit') || request()->routeIs('client.inventory.create') ? 'active' : '' }}">Barang & Jasa</a>
                                    <a href="{{ route('client.inventory.warehouses') }}" class="nav-child dot-blue {{ request()->routeIs('client.inventory.warehouses') ? 'active' : '' }}">Gudang</a>
                                    <a href="{{ route('client.inventory.units') }}" class="nav-child dot-blue {{ request()->routeIs('client.inventory.units') ? 'active' : '' }}">Satuan Barang</a>
                                    <a href="{{ route('client.inventory.categories') }}" class="nav-child dot-blue {{ request()->routeIs('client.inventory.categories*') ? 'active' : '' }}">Kategori Barang</a>
                                    <a href="{{ route('client.inventory.brands') }}" class="nav-child dot-blue {{ request()->routeIs('client.inventory.brands') ? 'active' : '' }}">Merek Barang</a>
                                    <a href="{{ route('client.inventory.items_per_warehouse') }}" class="nav-child dot-purple {{ request()->routeIs('client.inventory.items_per_warehouse') ? 'active' : '' }}">Barang per Gudang</a>
                                    <a href="{{ route('client.inventory.minimum_stock') }}" class="nav-child dot-purple {{ request()->routeIs('client.inventory.minimum_stock') ? 'active' : '' }}">Barang Stok Minimum</a>
                                </div>
                            </div>

                            {{-- Aset Tetap (Accordion) --}}
                            <div x-data="{ open: {{ request()->routeIs('client.fixed_assets.*') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4M4 15h16"></path></svg>
                                    Aset Tetap
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.fixed_assets.index') }}" class="nav-child dot-blue {{ request()->routeIs('client.fixed_assets.index') ? 'active' : '' }}">Aset Tetap</a>
                                    <a href="{{ route('client.fixed_assets.categories') }}" class="nav-child dot-blue {{ request()->routeIs('client.fixed_assets.categories') ? 'active' : '' }}">Kategori Aset</a>
                                    <a href="{{ route('client.fixed_assets.tax_categories') }}" class="nav-child dot-blue {{ request()->routeIs('client.fixed_assets.tax_categories') ? 'active' : '' }}">Kategori Aset Tetap Pajak</a>
                                    <a href="{{ route('client.fixed_assets.modifications') }}" class="nav-child dot-green {{ request()->routeIs('client.fixed_assets.modifications') ? 'active' : '' }}">Perubahan Aset Tetap</a>
                                    <a href="{{ route('client.fixed_assets.dispositions') }}" class="nav-child dot-green {{ request()->routeIs('client.fixed_assets.dispositions') ? 'active' : '' }}">Disposisi Aset Tetap</a>
                                    <a href="{{ route('client.fixed_assets.transfers') }}" class="nav-child dot-green {{ request()->routeIs('client.fixed_assets.transfers') ? 'active' : '' }}">Pindah Aset</a>
                                    <a href="{{ route('client.fixed_assets.locations') }}" class="nav-child dot-purple {{ request()->routeIs('client.fixed_assets.locations') ? 'active' : '' }}">Aset per Lokasi</a>
                                </div>
                            </div>


                            {{-- LAPORAN --}}
                            <div class="nav-section-label">Laporan</div>

                            <div x-data="{ open: {{ request()->routeIs('client.reports.*') && !request()->routeIs('client.reports.tax_calculator') ? 'true' : 'false' }} }">
                                <div class="nav-item nav-parent" :class="{ 'open': open }" @click="open = !open">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                                    Daftar Laporan
                                    <svg class="nav-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <div class="nav-children" :class="{ 'show': open }">
                                    <a href="{{ route('client.reports.index') }}" class="nav-child dot-purple {{ request()->routeIs('client.reports.index') ? 'active' : '' }}">Daftar Laporan</a>
                                    <a href="{{ route('client.reports.spt_ppn') }}" class="nav-child dot-purple {{ request()->routeIs('client.reports.spt_ppn') ? 'active' : '' }}">SPT PPN / PPNBM</a>
                                </div>
                            </div>
                            
                            {{-- ALAT BANTU --}}
                            <div class="nav-section-label">Alat Bantu</div>
                            
                            <a href="{{ route('client.reports.tax_calculator') }}" class="nav-item {{ request()->routeIs('client.reports.tax_calculator') ? 'active' : '' }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Kalkulator Pajak
                            </a>
                        @endif
                    </nav>

                    <!-- User Area -->
                    <div class="sidebar-user">
                        <div class="sidebar-user-info">
                            <div class="sidebar-user-avatar">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <div style="min-width:0; flex:1;">
                                <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                                <div class="sidebar-user-role">{{ auth()->user()->role == 'super_admin' ? 'Super Admin' : (auth()->user()->company ? auth()->user()->company->name : 'Client') }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="sidebar-logout-btn">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Top Header -->
                <header class="top-header">
                    <div class="top-header-left">
                        <button class="hamburger-btn" @click="sidebarOpen = !sidebarOpen" title="Toggle Sidebar">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <div class="top-header-title">
                            <h1 class="font-poppins">@yield('title')</h1>
                            <p>@yield('subtitle', 'Welcome back, ' . explode(' ', auth()->user()->name)[0])</p>
                        </div>
                    </div>
                    
                    <div class="top-header-right">
                        @if(!auth()->user()->isSuperAdmin() && auth()->user()->company)
                            <div class="company-badge">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                {{ auth()->user()->company->name }}
                            </div>
                        @endif

                        @if(!auth()->user()->isSuperAdmin())
                            @include('partials.global-search')
                        @endif
                        @include('partials.theme-toggle')

                        <a href="{{ route('profile.edit') }}" class="header-icon-btn" data-tooltip="Profil Akun">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </a>
                        
                        <!-- Notifikasi Dropdown -->
                        <div x-data="{ notifOpen: false }" class="relative">
                            <button @click="notifOpen = !notifOpen" @click.away="notifOpen = false" class="header-icon-btn" data-tooltip="Notifikasi">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                <!-- Badge Unread (Opsional) -->
                                <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-[#0a0f1e]"></span>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div x-show="notifOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 transform -translate-y-2" x-transition:enter-end="opacity-100 scale-100 transform translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-3 w-80 bg-[#1e293b] border border-gray-700 rounded-xl shadow-2xl z-50 overflow-hidden" style="display: none;">
                                <div class="px-4 py-3 border-b border-gray-700 flex justify-between items-center bg-[#0f172a]">
                                    <h3 class="text-sm font-semibold text-white">Notifikasi Sistem</h3>
                                    <span class="text-xs text-trusta-teal cursor-pointer hover:text-white">Tandai dibaca</span>
                                </div>
                                <div class="max-h-80 overflow-y-auto">
                                    <div class="px-4 py-4 text-center text-sm text-gray-500 flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 mb-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        Belum ada notifikasi baru untuk saat ini.
                                    </div>
                                    <!-- Jika ada notif, formatnya seperti ini:
                                    <a href="#" class="px-4 py-3 border-b border-gray-700/50 hover:bg-[#0f172a] block transition-colors">
                                        <p class="text-sm text-gray-200">Pesanan Penjualan <span class="font-bold text-trusta-teal">SO-001</span> telah disetujui.</p>
                                        <p class="text-xs text-gray-500 mt-1">2 jam yang lalu</p>
                                    </a>
                                    -->
                                </div>
                                <a href="#" class="block px-4 py-2 text-center text-xs font-medium text-gray-400 hover:text-white hover:bg-[#0f172a] border-t border-gray-700 transition-colors">Lihat Semua Notifikasi</a>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Tab Bar -->
                <div class="app-tabs-container" x-data="{
                    storageKey: 'app_tabs_user_{{ auth()->id() }}',
                    tabs: [],
                    currentUrl: window.location.href.split('?')[0],
                    init() {
                        this.tabs = JSON.parse(sessionStorage.getItem(this.storageKey)) || [];
                        let dashboardUrl = '{{ auth()->check() && auth()->user()->isSuperAdmin() ? route('superadmin.dashboard') : route('client.dashboard') }}';
                        
                        if(this.tabs.length === 0) {
                            this.tabs.push({ title: 'Dashboard', url: dashboardUrl, active: false });
                        }
                        
                        let exists = this.tabs.findIndex(t => t.url.split('?')[0] === this.currentUrl);
                        let title = document.querySelector('.top-header-title h1')?.innerText || 'Halaman';
                        
                        if (exists === -1 && this.currentUrl !== dashboardUrl) {
                            this.tabs.push({title: title, url: this.currentUrl, active: true});
                        }
                        
                        this.tabs = this.tabs.map((t, index) => {
                            let isActive = (t.url.split('?')[0] === this.currentUrl);
                            if (isActive) t.title = title; // update title
                            return {...t, active: isActive};
                        });
                        
                        sessionStorage.setItem(this.storageKey, JSON.stringify(this.tabs));
                    },
                    closeTab(index, event) {
                        event.preventDefault();
                        event.stopPropagation();
                        let tab = this.tabs[index];
                        this.tabs.splice(index, 1);
                        sessionStorage.setItem(this.storageKey, JSON.stringify(this.tabs));
                        
                        if (tab.active && this.tabs.length > 0) {
                            window.location.href = this.tabs[this.tabs.length - 1].url;
                        } else if (this.tabs.length === 0) {
                            let dashboardUrl = '{{ auth()->check() && auth()->user()->isSuperAdmin() ? route('superadmin.dashboard') : route('client.dashboard') }}';
                            window.location.href = dashboardUrl;
                        }
                    }
                }">
                    <template x-for="(tab, index) in tabs" :key="index">
                        <a :href="tab.url" class="app-tab" :class="{ 'active': tab.active }">
                            <span x-text="tab.title"></span>
                            <button x-show="tab.title !== 'Dashboard'" @click="closeTab(index, $event)" class="app-tab-close" title="Tutup">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </a>
                    </template>
                </div>

                <!-- Page Content -->
                <main class="page-content">
                    @if (session('success'))
                        <div class="alert-success">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert-error">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p>{!! session('error') !!}</p>
                        </div>
                    @endif

                    <div class="fade-in">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
