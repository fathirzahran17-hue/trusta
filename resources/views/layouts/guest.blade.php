<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TRUSTA') }} - Auth</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Tema terang/gelap (diterapkan sebelum render agar tidak berkedip) -->
        @include('partials.theme-head')

        <!-- Scripts (Alpine.js sudah dibundel di app.js) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-body font-sans antialiased">
        @include('partials.theme-toggle', ['floating' => true])

        <div class="auth-shell">
            <aside class="auth-brand">
                <div class="auth-brand-inner">
                    <div>
                        <div class="auth-logo">TRUST<span>A</span></div>
                        <div class="auth-logo-line"></div>
                        <div class="auth-tagline">Trusted Accounting Solution</div>

                        <h2 class="auth-headline">Pembukuan yang rapi, dari pesanan sampai laporan.</h2>
                        <p class="auth-lead">Satu tempat untuk penjualan, pembelian, persediaan, kas &amp; bank, hingga laporan keuangan. Semua saling terhubung.</p>

                        <ul class="auth-points">
                            <li>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Pesanan, pengiriman, faktur, dan pembayaran tertaut otomatis
                            </li>
                            <li>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Jurnal akuntansi dibuat otomatis dari setiap transaksi
                            </li>
                            <li>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Laporan laba rugi, neraca, dan umur piutang siap pakai
                            </li>
                        </ul>
                    </div>
                    <div class="auth-brand-foot">&copy; {{ date('Y') }} TRUSTA. Trusted Accounting Solution.</div>
                </div>
            </aside>

            <main class="auth-main">
                <div class="auth-mobile-brand">
                    <div class="auth-logo">TRUST<span>A</span></div>
                    <div class="auth-tagline" style="margin-top:6px;">Trusted Accounting Solution</div>
                </div>
                <div class="auth-card">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
