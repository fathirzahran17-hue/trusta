<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TRUSTA') }} - @yield('title', 'Trusted Accounting Solution')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700|poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Alpine.js (Bypass Hosting Restrictions) -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <!-- TomSelect for searchable dropdowns -->
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

        <style>
            /* ===== TRUSTA Design System ===== */
            :root {
                --trusta-navy: #0a0f1e;
                --trusta-navy-light: #111827;
                --trusta-sidebar: #0d1321;
                --trusta-teal: #14B8A6;
                --trusta-teal-light: #2dd4bf;
                --trusta-gold: #C9A84C;
                --trusta-gray: #94A3B8;
                --trusta-surface: #1a1f2e;
                --trusta-card: #1e293b;
                --trusta-dark: #111827;
                --trusta-border: rgba(255,255,255,0.06);
                --sidebar-width: 250px;
                --sidebar-collapsed: 0px;
                --header-height: 52px;
            }

            * { box-sizing: border-box; }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: var(--trusta-navy);
                color: #e2e8f0;
                margin: 0;
                overflow: hidden;
                height: 100vh;
            }

            h1, h2, h3, h4, h5, h6, .font-poppins {
                font-family: 'Poppins', sans-serif;
            }

            /* ===== Color Utilities ===== */
            .bg-trusta-navy { background-color: var(--trusta-navy); }
            .bg-trusta-navy-light { background-color: var(--trusta-navy-light); }
            .bg-trusta-surface { background-color: var(--trusta-surface); }
            .text-trusta-teal { color: var(--trusta-teal); }
            .text-trusta-gold { color: var(--trusta-gold); }
            .border-trusta-teal { border-color: var(--trusta-teal); }
            .border-trusta-gold { border-color: var(--trusta-gold); }

            /* ===== Layout ===== */
            .app-layout {
                display: flex;
                height: 100vh;
                overflow: hidden;
            }

            /* ===== Sidebar ===== */
            .sidebar {
                width: var(--sidebar-width);
                min-width: var(--sidebar-width);
                background: linear-gradient(180deg, #0d1321 0%, #0a0f1e 100%);
                border-right: 1px solid var(--trusta-border);
                display: flex;
                flex-direction: column;
                height: 100vh;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 40;
                overflow: hidden;
            }

            .sidebar.collapsed {
                width: 0;
                min-width: 0;
                border-right: none;
            }

            .sidebar.collapsed .sidebar-inner {
                opacity: 0;
                pointer-events: none;
            }

            .sidebar-inner {
                display: flex;
                flex-direction: column;
                height: 100%;
                width: var(--sidebar-width);
                transition: opacity 0.2s ease;
            }

            /* Logo */
            .sidebar-logo {
                height: var(--header-height);
                display: flex;
                align-items: center;
                justify-content: center;
                border-bottom: 1px solid var(--trusta-border);
                flex-shrink: 0;
            }

            .sidebar-logo a {
                text-decoration: none;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
            }

            .sidebar-logo .logo-text {
                font-family: 'Poppins', sans-serif;
                font-size: 1.15rem;
                font-weight: 800;
                letter-spacing: 0.15em;
                color: #fff;
            }

            .sidebar-logo .logo-text span { color: var(--trusta-gold); }

            .sidebar-logo .logo-sub {
                font-size: 0.45rem;
                letter-spacing: 0.2em;
                text-transform: uppercase;
                color: #64748b;
            }

            /* Nav Section */
            .sidebar-nav {
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
                padding: 12px 10px;
            }

            .sidebar-nav::-webkit-scrollbar { width: 3px; }
            .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
            .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 10px; }

            .nav-section-label {
                font-size: 0.6rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: #475569;
                padding: 8px 12px 4px;
                margin-top: 8px;
            }

            .nav-section-label:first-child { margin-top: 0; }

            /* Nav Item */
            .nav-item {
                display: flex;
                align-items: center;
                padding: 7px 12px;
                border-radius: 8px;
                color: #94a3b8;
                font-size: 0.8rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.15s ease;
                margin: 1px 0;
                cursor: pointer;
                position: relative;
                white-space: nowrap;
            }

            .nav-item:hover {
                background: rgba(255,255,255,0.04);
                color: #e2e8f0;
            }

            .nav-item.active {
                background: linear-gradient(135deg, rgba(20,184,166,0.12), rgba(20,184,166,0.05));
                color: var(--trusta-teal);
                font-weight: 600;
            }

            .nav-item.active::before {
                content: '';
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                width: 3px;
                height: 60%;
                background: var(--trusta-teal);
                border-radius: 0 3px 3px 0;
            }

            .nav-item svg, .nav-item .nav-icon {
                width: 17px;
                height: 17px;
                margin-right: 10px;
                flex-shrink: 0;
                opacity: 0.7;
            }

            .nav-item.active svg, .nav-item.active .nav-icon {
                opacity: 1;
                color: var(--trusta-teal);
            }

            /* Sub-menu parent */
            .nav-parent {
                user-select: none;
            }

            .nav-parent .nav-chevron {
                width: 14px;
                height: 14px;
                margin-left: auto;
                transition: transform 0.2s ease;
                opacity: 0.5;
            }

            .nav-parent.open .nav-chevron {
                transform: rotate(180deg);
            }

            /* Sub-menu children */
            .nav-children {
                overflow: hidden;
                max-height: 0;
                transition: max-height 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .nav-children.show {
                max-height: 400px;
            }

            .nav-child {
                display: flex;
                align-items: center;
                padding: 6px 12px 6px 40px;
                border-radius: 6px;
                color: #64748b;
                font-size: 0.75rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.15s ease;
                margin: 0 0 1px;
                position: relative;
            }

            .nav-child::before {
                content: '';
                position: absolute;
                left: 22px;
                top: 50%;
                transform: translateY(-50%);
                width: 4px;
                height: 4px;
                border-radius: 50%;
                background: #334155;
                transition: background 0.15s ease;
            }

            .nav-child:hover {
                color: #cbd5e1;
                background: rgba(255,255,255,0.03);
            }

            .nav-child:hover::before {
                background: #94a3b8;
            }

            .nav-child.active {
                color: var(--trusta-teal);
                font-weight: 600;
            }

            .nav-child.active::before {
                background: var(--trusta-teal);
                box-shadow: 0 0 6px rgba(20,184,166,0.5);
            }

            .nav-child.dot-orange::before { background: #f97316; box-shadow: 0 0 4px rgba(249,115,22,0.5); }
            .nav-child.dot-blue::before { background: #3b82f6; box-shadow: 0 0 4px rgba(59,130,246,0.5); }
            .nav-child.dot-green::before { background: #22c55e; box-shadow: 0 0 4px rgba(34,197,94,0.5); }
            .nav-child.dot-purple::before { background: #a855f7; box-shadow: 0 0 4px rgba(168,85,247,0.5); }

            /* Sidebar User */
            .sidebar-user {
                padding: 10px 12px;
                border-top: 1px solid var(--trusta-border);
                flex-shrink: 0;
            }

            .sidebar-user-info {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 8px;
            }

            .sidebar-user-avatar {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                background: linear-gradient(135deg, var(--trusta-teal), #0d9488);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-weight: 700;
                font-size: 0.75rem;
                flex-shrink: 0;
            }

            .sidebar-user-name {
                font-size: 0.78rem;
                font-weight: 600;
                color: #e2e8f0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar-user-role {
                font-size: 0.62rem;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar-logout-btn {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 6px;
                border-radius: 6px;
                border: 1px solid rgba(255,255,255,0.06);
                background: rgba(255,255,255,0.02);
                color: #64748b;
                font-size: 0.72rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .sidebar-logout-btn:hover {
                background: rgba(239,68,68,0.1);
                border-color: rgba(239,68,68,0.2);
                color: #f87171;
            }

            .sidebar-logout-btn svg {
                width: 14px;
                height: 14px;
            }

            /* ===== Main Content ===== */
            .main-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                height: 100vh;
                overflow: hidden;
                min-width: 0;
            }

            /* Top Header */
            .top-header {
                height: var(--header-height);
                background: rgba(10, 15, 30, 0.85);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-bottom: 1px solid var(--trusta-border);
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 24px;
                flex-shrink: 0;
                z-index: 30;
            }

            .top-header-left {
                display: flex;
                align-items: center;
                gap: 14px;
                min-width: 0;
            }

            /* Hamburger */
            .hamburger-btn {
                width: 34px;
                height: 34px;
                border-radius: 8px;
                border: 1px solid var(--trusta-border);
                background: rgba(255,255,255,0.03);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: #94a3b8;
                transition: all 0.15s ease;
                flex-shrink: 0;
            }

            .hamburger-btn:hover {
                background: rgba(255,255,255,0.06);
                color: #e2e8f0;
            }

            .hamburger-btn svg {
                width: 18px;
                height: 18px;
            }

            .top-header-title h1 {
                font-size: 1rem;
                font-weight: 600;
                color: #f1f5f9;
                margin: 0;
                line-height: 1.3;
            }

            .top-header-title p {
                font-size: 0.68rem;
                color: #64748b;
                margin: 0;
            }

            .top-header-right {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .header-icon-btn {
                width: 34px;
                height: 34px;
                border-radius: 8px;
                border: 1px solid var(--trusta-border);
                background: rgba(255,255,255,0.03);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: #64748b;
                transition: all 0.15s ease;
            }

            .header-icon-btn:hover {
                background: rgba(255,255,255,0.06);
                color: #e2e8f0;
            }

            .header-icon-btn svg {
                width: 16px;
                height: 16px;
            }

            /* Page Content */
            .page-content {
                flex: 1;
                overflow-x: hidden;
                overflow-y: auto;
                padding: 20px 24px;
                background: linear-gradient(180deg, #0a0f1e 0%, #0e1525 100%);
            }

            .page-content::-webkit-scrollbar { width: 5px; }
            .page-content::-webkit-scrollbar-track { background: transparent; }
            .page-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 10px; }

            /* ===== Alert Messages ===== */
            .alert-success {
                margin-bottom: 16px;
                padding: 10px 14px;
                background: rgba(20,184,166,0.08);
                border: 1px solid rgba(20,184,166,0.2);
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 10px;
                color: var(--trusta-teal);
                font-size: 0.82rem;
                font-weight: 500;
            }

            .alert-success svg { width: 18px; height: 18px; flex-shrink: 0; }

            .alert-error {
                margin-bottom: 16px;
                padding: 10px 14px;
                background: rgba(239,68,68,0.08);
                border: 1px solid rgba(239,68,68,0.2);
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #f87171;
                font-size: 0.82rem;
                font-weight: 500;
            }

            .alert-error svg { width: 18px; height: 18px; flex-shrink: 0; }

            /* ===== Global Component Overrides ===== */

            /* Compact form inputs */
            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="date"],
            input[type="password"],
            input[type="tel"],
            input[type="url"],
            select,
            textarea {
                width: 100%;
                font-size: 0.8rem !important;
                padding: 6px 10px !important;
                border-radius: 6px !important;
                background-color: rgba(15, 23, 42, 0.6) !important;
                border: 1px solid rgba(255,255,255,0.08) !important;
                color: #e2e8f0 !important;
                transition: all 0.15s ease !important;
                line-height: 1.5 !important;
            }

            input:focus, select:focus, textarea:focus {
                border-color: var(--trusta-teal) !important;
                box-shadow: 0 0 0 2px rgba(20,184,166,0.15) !important;
                outline: none !important;
            }

            textarea {
                min-height: 60px !important;
            }

            /* Labels */
            label {
                font-size: 0.75rem !important;
                font-weight: 500;
                color: #94a3b8;
                margin-bottom: 4px !important;
            }

            /* Tables */
            table { font-size: 0.8rem; }

            table thead th {
                font-size: 0.65rem !important;
                letter-spacing: 0.05em;
                padding: 8px 14px !important;
            }

            table tbody td {
                padding: 8px 14px !important;
            }

            /* Cards / Panels */
            .rounded-2xl { border-radius: 10px !important; }

            /* Buttons */
            .btn-primary {
                padding: 7px 16px;
                background: var(--trusta-teal);
                color: #0a0f1e;
                font-size: 0.78rem;
                font-weight: 600;
                border-radius: 7px;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: all 0.15s ease;
                text-decoration: none;
            }

            .btn-primary:hover {
                background: var(--trusta-teal-light);
                box-shadow: 0 4px 12px rgba(20,184,166,0.25);
            }

            /* Mobile overlay */
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 35;
                backdrop-filter: blur(4px);
            }

            /* ===== Responsive ===== */
            @media (max-width: 768px) {
                .sidebar {
                    position: fixed;
                    left: 0;
                    top: 0;
                    height: 100vh;
                    z-index: 40;
                }

                .sidebar.collapsed {
                    transform: translateX(-100%);
                    width: var(--sidebar-width);
                    min-width: var(--sidebar-width);
                }

                .sidebar-overlay.show {
                    display: block;
                }
            }

            /* Smooth transition for grid and content */
            .fade-in {
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Badge Styles */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 0.65rem;
                font-weight: 600;
                letter-spacing: 0.02em;
            }

            .badge-success {
                background: rgba(20,184,166,0.1);
                color: var(--trusta-teal);
                border: 1px solid rgba(20,184,166,0.2);
            }

            .badge-danger {
                background: rgba(239,68,68,0.1);
                color: #f87171;
                border: 1px solid rgba(239,68,68,0.2);
            }

            /* Tooltip */
            [data-tooltip] {
                position: relative;
            }

            [data-tooltip]:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                padding: 4px 8px;
                background: #1e293b;
                color: #e2e8f0;
                font-size: 0.7rem;
                border-radius: 4px;
                white-space: nowrap;
                margin-bottom: 4px;
                border: 1px solid var(--trusta-border);
            }

            /* Company badge in header */
            .company-badge {
                display: none;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                border-radius: 6px;
                background: rgba(201, 168, 76, 0.08);
                border: 1px solid rgba(201, 168, 76, 0.15);
                font-size: 0.68rem;
                font-weight: 500;
                color: var(--trusta-gold);
            }

            @media (min-width: 768px) {
                .company-badge { display: flex; }
            }

            /* Tab System */
            .app-tabs-container {
                display: flex;
                background: #f1f5f9;
                padding: 12px 16px 0; /* Tambah padding atas jadi 12px agar tidak terpotong */
                gap: 4px;
                border-bottom: 1px solid #e2e8f0;
                overflow-x: auto;
                scrollbar-width: none;
            }
            .app-tabs-container::-webkit-scrollbar { display: none; }
            .dark .app-tabs-container {
                background: var(--trusta-darker);
                border-bottom: 1px solid var(--trusta-border);
            }
            .app-tab {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: #e2e8f0;
                color: #64748b;
                border-radius: 8px 8px 0 0;
                font-size: 0.8rem;
                font-weight: 600;
                text-decoration: none;
                white-space: nowrap;
                transition: all 0.2s;
                border: 1px solid transparent;
                border-bottom: none;
            }
            .dark .app-tab {
                background: var(--trusta-dark);
                color: #9ca3af;
            }
            .app-tab:hover {
                background: #cbd5e1;
                color: #334155;
            }
            .dark .app-tab:hover {
                background: #374151;
                color: #e2e8f0;
            }
            .app-tab.active {
                background: #fff;
                color: #f43f5e;
                border-color: #e2e8f0;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.02);
            }
            .dark .app-tab.active {
                background: var(--trusta-bg);
                color: var(--trusta-teal);
                border-color: var(--trusta-border);
            }
            .app-tab-close {
                width: 16px;
                height: 16px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                color: inherit;
                opacity: 0.6;
                transition: all 0.2s;
            }
            .app-tab-close:hover {
                opacity: 1;
                background: rgba(0,0,0,0.1);
            }
            .dark .app-tab-close:hover {
                background: rgba(255,255,255,0.1);
            }
            .app-tab.active .app-tab-close {
                background: rgba(244, 63, 94, 0.1);
                color: #f43f5e;
            }
            .dark .app-tab.active .app-tab-close {
                background: rgba(20, 184, 166, 0.1);
                color: var(--trusta-teal);
            }
            .app-tab.active .app-tab-close:hover {
                background: #f43f5e;
                color: #fff;
            }
            .dark .app-tab.active .app-tab-close:hover {
                background: var(--trusta-teal);
                color: var(--trusta-darker);
            }

            /* ===== TOM SELECT DARK THEME OVERRIDE ===== */
            .ts-control {
                background-color: transparent !important;
                border: none !important;
                color: #e2e8f0 !important;
                padding: 8px 12px !important;
                min-height: 38px !important;
                box-shadow: none !important;
                border-radius: 6px !important;
            }
            .ts-control.focus {
                background-color: rgba(30,41,59, 0.5) !important;
                box-shadow: 0 0 0 2px var(--trusta-teal) !important;
            }
            .ts-wrapper.single .ts-control {
                background-color: transparent;
            }
            .ts-control > input {
                color: #e2e8f0 !important;
            }
            .ts-dropdown {
                background-color: #1e293b !important;
                border: 1px solid #334155 !important;
                color: #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
            }
            .ts-dropdown .ts-dropdown-content {
                max-height: 250px !important;
            }
            .ts-dropdown .option {
                padding: 8px 12px !important;
                cursor: pointer;
            }
            .ts-dropdown .option:hover,
            .ts-dropdown .active {
                background-color: var(--trusta-teal) !important;
                color: #0f172a !important;
            }
            .ts-control, .ts-wrapper.single.input-active .ts-control {
                background: var(--trusta-surface);
            }
            td .ts-wrapper {
                width: 100%;
            }
            td .ts-control {
                border-radius: 4px !important;
            }
        </style>
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
