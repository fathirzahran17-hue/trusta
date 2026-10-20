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
        <script>
            // Daftar pilihan dirender di <body> agar tidak terpotong kartu/tabel (overflow hidden) dan selalu terlihat penuh.
            (function () {
                var Orig = window.TomSelect; if (!Orig) return;
                var Wrapped = function (el, opts) { return new Orig(el, Object.assign({ dropdownParent: 'body', maxOptions: 1000, create: false }, opts || {})); };
                Wrapped.prototype = Orig.prototype; Object.assign(Wrapped, Orig); window.TomSelect = Wrapped;
            })();
        </script>

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
                            <div class="logo-row">
                                <img src="{{ asset('favicon.svg') }}" alt="" class="logo-mark" width="26" height="26">
                                <div class="logo-text">TRUST<span>A</span></div>
                            </div>
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
                            @include('partials.sidebar-menu')
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
                                @if(auth()->user()->company->logo_url)
                                    <img src="{{ auth()->user()->company->logo_url }}" alt="" class="company-badge-logo">
                                @else
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                @endif
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
