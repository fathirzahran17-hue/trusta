<x-app-layout>
@section('title', 'Daftar Laporan')

<style>
    .report-container {
        display: flex;
        background: #0f172a;
        border-radius: 12px;
        overflow: hidden;
        height: calc(100vh - 120px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    .report-sidebar {
        width: 260px;
        background: #1e293b;
        border-right: 1px solid #334155;
        overflow-y: auto;
        padding-top: 20px;
    }
    .r-menu-item {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        color: #94a3b8;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
        border-left: 4px solid transparent;
        cursor: pointer;
    }
    .r-menu-item:hover {
        background: #0f172a;
        color: #e2e8f0;
    }
    .r-menu-item.active {
        background: rgba(20, 184, 166, 0.1);
        color: #14b8a6;
        border-left-color: #14b8a6;
    }
    .r-menu-item i, .r-menu-item svg {
        margin-right: 14px;
        width: 22px;
        height: 22px;
    }
    .report-main {
        flex: 1;
        padding: 30px 40px;
        overflow-y: auto;
        background: #0f172a;
    }
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px;
        border-bottom: 1px solid #334155;
        padding-bottom: 20px;
    }
    .report-header h1 {
        font-size: 28px;
        color: #f8fafc;
        font-weight: 600;
        margin: 0;
        letter-spacing: -0.5px;
    }
    
    .section-title {
        font-size: 22px;
        color: #e2e8f0;
        margin: 32px 0 20px 0;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-title::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #1e293b;
        margin-left: 15px;
    }
    
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 24px;
    }
    
    .report-card {
        display: flex;
        align-items: flex-start;
        padding: 24px;
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }
    .report-card::before {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 4px; height: 100%;
        background: #334155;
        transition: all 0.3s;
    }
    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.3);
        border-color: #475569;
    }
    
    .card-integrated::before { background: #10b981; }
    .card-integrated:hover { border-color: #10b981; box-shadow: 0 12px 20px rgba(16, 185, 129, 0.15); }
    
    .card-profit::before { background: #0ea5e9; }
    .card-profit:hover { border-color: #0ea5e9; box-shadow: 0 12px 20px rgba(14, 165, 233, 0.15); }
    
    .card-balance::before { background: #a855f7; }
    .card-balance:hover { border-color: #a855f7; box-shadow: 0 12px 20px rgba(168, 85, 247, 0.15); }
    
    .card-gl::before { background: #f59e0b; }
    .card-gl:hover { border-color: #f59e0b; box-shadow: 0 12px 20px rgba(245, 158, 11, 0.15); }

    .card-pink::before { background: #ec4899; }
    .card-pink:hover { border-color: #ec4899; box-shadow: 0 12px 20px rgba(236, 72, 153, 0.15); }

    .card-red::before { background: #ef4444; }
    .card-red:hover { border-color: #ef4444; box-shadow: 0 12px 20px rgba(239, 68, 68, 0.15); }

    .card-teal::before { background: #14b8a6; }
    .card-teal:hover { border-color: #14b8a6; box-shadow: 0 12px 20px rgba(20, 184, 166, 0.15); }
    
    .report-icon {
        width: 48px;
        height: 48px;
        margin-right: 20px;
        flex-shrink: 0;
        padding: 10px;
        border-radius: 10px;
        background: rgba(15, 23, 42, 0.6);
    }
    
    .icon-integrated { stroke: #10b981; }
    .icon-keuangan { stroke: #0ea5e9; } 
    .icon-buku-besar { stroke: #f59e0b; }
    .icon-pajak { stroke: #a855f7; }
    .icon-penjualan { stroke: #ec4899; }
    .icon-hutang { stroke: #ef4444; }
    .icon-persediaan { stroke: #14b8a6; }
    
    .report-info h3 {
        margin: 0 0 6px 0;
        font-size: 16px;
        color: #f8fafc;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .report-info p {
        margin: 0;
        font-size: 13px;
        color: #94a3b8;
        line-height: 1.5;
    }
    
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #0f172a; }
    ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>

<div class="report-container">
    <!-- Sidebar Kiri -->
    <div class="report-sidebar">
        <a href="#keuangan" class="r-menu-item active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Keuangan
        </a>
        <a href="#buku-besar" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            Buku Besar
        </a>
        <a href="#piutang" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Piutang
        </a>
        <a href="#penjualan" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            Penjualan
        </a>
        <a href="#hutang" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
            Utang
        </a>
        <a href="#pembelian" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            Pembelian
        </a>
        <a href="#persediaan" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            Persediaan
        </a>
        <a href="#pajak" class="r-menu-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            Pajak
        </a>
    </div>

    <!-- Area Utama -->
    <div class="report-main">
        <div class="report-header">
            <h1>Laporan Keuangan & Akuntansi</h1>
        </div>

        <!-- === KEUANGAN === -->
        <h2 id="keuangan" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Keuangan
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.integrated') }}" class="report-card card-integrated">
                <svg class="report-icon icon-integrated" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
                </svg>
                <div class="report-info">
                    <h3>Laporan Terpadu (Gabungan)</h3>
                    <p>Laba Rugi + Neraca dalam satu tampilan interaktif</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.profit-loss') }}" class="report-card card-profit">
                <svg class="report-icon icon-keuangan" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>Laba Rugi</h3>
                    <p>Kinerja pendapatan vs beban untuk periode tertentu</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.balance-sheet') }}" class="report-card card-balance">
                <svg class="report-icon icon-pajak" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                </svg>
                <div class="report-info">
                    <h3>Neraca (Balance Sheet)</h3>
                    <p>Posisi keuangan: Aset, Kewajiban, Ekuitas</p>
                </div>
            </a>
        </div>

        <!-- === BUKU BESAR === -->
        <h2 id="buku-besar" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            Buku Besar
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.general-ledger') }}" class="report-card card-gl">
                <svg class="report-icon icon-buku-besar" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>General Ledger (Buku Besar)</h3>
                    <p>Rincian mutasi debit/kredit setiap akun COA</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.trial-balance') }}" class="report-card card-gl">
                <svg class="report-icon icon-buku-besar" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                </svg>
                <div class="report-info">
                    <h3>Neraca Saldo (Trial Balance)</h3>
                    <p>Daftar saldo semua akun — memastikan debit = kredit</p>
                </div>
            </a>
        </div>

        <!-- === PIUTANG === -->
        <h2 id="piutang" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Piutang & Pelanggan
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.receivable-aging') }}" class="report-card card-profit">
                <svg class="report-icon icon-keuangan" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="report-info">
                    <h3>Umur Piutang (Aging)</h3>
                    <p>Analisis piutang berdasarkan umur jatuh tempo per pelanggan</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.receivable-summary') }}" class="report-card card-profit">
                <svg class="report-icon icon-keuangan" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <div class="report-info">
                    <h3>Rekap Piutang per Pelanggan</h3>
                    <p>Total sisa piutang masing-masing pelanggan</p>
                </div>
            </a>
        </div>

        <!-- === PENJUALAN === -->
        <h2 id="penjualan" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            Penjualan
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.sales-report') }}" class="report-card card-pink">
                <svg class="report-icon icon-penjualan" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>Daftar Faktur Penjualan</h3>
                    <p>Rekapitulasi seluruh faktur penjualan yang diterbitkan</p>
                </div>
            </a>
        </div>

        <!-- === UTANG === -->
        <h2 id="hutang" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
            Utang & Pemasok
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.payable-aging') }}" class="report-card card-red">
                <svg class="report-icon icon-hutang" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="report-info">
                    <h3>Umur Hutang (Aging)</h3>
                    <p>Analisis hutang berdasarkan umur jatuh tempo per pemasok</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.payable-summary') }}" class="report-card card-red">
                <svg class="report-icon icon-hutang" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <div class="report-info">
                    <h3>Rekap Hutang per Pemasok</h3>
                    <p>Total sisa hutang masing-masing pemasok</p>
                </div>
            </a>
        </div>

        <!-- === PEMBELIAN === -->
        <h2 id="pembelian" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            Pembelian
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.purchase-report') }}" class="report-card card-balance">
                <svg class="report-icon icon-pajak" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>Daftar Faktur Pembelian</h3>
                    <p>Rekapitulasi seluruh faktur pembelian dari pemasok</p>
                </div>
            </a>
        </div>

        <!-- === PERSEDIAAN === -->
        <h2 id="persediaan" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            Persediaan
        </h2>
        <div class="report-grid mb-8">
            <a href="{{ route('client.reports.inventory-stock') }}" class="report-card card-teal">
                <svg class="report-icon icon-persediaan" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <div class="report-info">
                    <h3>Kartu Stok Persediaan</h3>
                    <p>Sisa stok dan mutasi keluar masuk barang</p>
                </div>
            </a>
        </div>

        <!-- === PAJAK === -->
        <h2 id="pajak" class="section-title">
            <svg style="width: 24px; height: 24px; color: #14b8a6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            Pajak
        </h2>
        <div class="report-grid mb-12">
            <a href="{{ route('client.reports.spt_ppn') }}" class="report-card card-balance">
                <svg class="report-icon icon-pajak" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>SPT PPN / PPNBM</h3>
                    <p>Rekapitulasi pajak keluaran dan masukan per periode</p>
                </div>
            </a>
            
            <a href="{{ route('client.reports.tax_calculator') }}" class="report-card card-balance">
                <svg class="report-icon icon-pajak" fill="none" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <div class="report-info">
                    <h3>Kalkulator Pajak</h3>
                    <p>Alat bantu hitung PPh 21, PPh 23, dan PPN</p>
                </div>
            </a>
        </div>
        
    </div>
</div>

<script>
    document.querySelectorAll('.r-menu-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.r-menu-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
        });
    });
</script>
</x-app-layout>
