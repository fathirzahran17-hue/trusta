<x-app-layout>
@section('title', 'SPT PPN / PPNBM')

<style>
    .spt-container {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 20px;
        min-height: calc(100vh - 120px);
    }
    .spt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .spt-filters {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .filter-group label {
        font-size: 14px;
        color: #334155;
        font-weight: 500;
    }
    .filter-group input[type="date"], .filter-group select, .filter-group input[type="text"] {
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        padding: 6px 10px;
        outline: none;
        color: #334155;
        font-size: 14px;
    }
    .filter-group input[type="date"]:focus, .filter-group select:focus, .filter-group input[type="text"]:focus {
        border-color: #3b82f6;
    }
    .btn-refresh {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        color: #3b82f6;
    }
    .btn-save {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 10px;
        border-radius: 4px;
        cursor: pointer;
        color: #94a3b8;
    }
    .btn-save:hover {
        background: #e2e8f0;
    }
    .btn-efaktur {
        background: #65a30d; /* lime-600 */
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-efaktur:hover {
        background: #4d7c0f;
    }
    
    .spt-content {
        margin-top: 20px;
    }
    .spt-tabs {
        display: flex;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 15px;
    }
    .spt-tab {
        padding: 10px 20px;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }
    .spt-tab.active {
        color: #e11d48;
        border-bottom-color: #e11d48;
    }
    
    .spt-table-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .spt-table-title {
        font-size: 18px;
        font-style: italic;
        color: #334155;
    }
    
    .spt-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e2e8f0;
    }
    .spt-table th {
        background: #475569;
        color: white;
        text-align: left;
        padding: 10px;
        font-weight: 500;
        font-size: 13px;
        border: 1px solid #e2e8f0;
    }
    .spt-table td {
        padding: 12px 10px;
        border: 1px solid #e2e8f0;
        color: #334155;
        font-size: 14px;
    }
    .spt-table .empty-state {
        text-align: center;
        color: #64748b;
        font-weight: 500;
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 20px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #3b82f6;
        transition: .4s;
        border-radius: 20px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #3b82f6;
    }
    input:focus + .slider {
        box-shadow: 0 0 1px #3b82f6;
    }
    input:checked + .slider:before {
        transform: translateX(20px);
    }
</style>

<div class="mb-6 flex items-center px-2">
    <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-navy-900 flex items-center text-sm font-medium transition-colors">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Kembali ke Daftar Laporan
    </a>
</div>

<div class="spt-container">
    <div class="spt-header">
        <div class="spt-filters">
            <div class="filter-group">
                <label>Tgl Pajak <span class="text-red-500">*</span></label>
                <input type="date" value="{{ date('Y-m-d') }}">
                <span>s/d <span class="text-red-500">*</span></span>
                <input type="date" value="{{ date('Y-m-d') }}">
                <button class="btn-refresh">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
            </div>
            
            <div class="filter-group" style="margin-left: auto;">
                <label>Nomor # <span class="text-red-500">*</span></label>
                <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="slider"></span>
                </label>
                <select>
                    <option>SPT PPN/PPNBM</option>
                </select>
            </div>
            
            <button class="btn-save">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            </button>
        </div>
    </div>
    
    <div class="spt-filters" style="margin-bottom: 20px;">
        <div class="filter-group">
            <label>Tipe</label>
            <select style="width: 250px;">
                <option>Pajak Masukan dan Keluaran</option>
                <option>Pajak Masukan</option>
                <option>Pajak Keluaran</option>
            </select>
        </div>
        <div style="margin-left: auto;">
            <button class="btn-efaktur">
                eFaktur
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>
    </div>

    <div class="spt-content">
        <div class="spt-tabs">
            <div class="spt-tab active">
                <svg style="width: 16px; height: 16px; display: inline-block; margin-right: 5px; stroke: #e11d48;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div class="spt-tab" style="padding-left: 10px;">
                <svg style="width: 16px; height: 16px; display: inline-block; stroke: #64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
        </div>

        <div class="spt-table-toolbar">
            <div class="spt-table-title">Pajak Keluaran</div>
            <div class="filter-group">
                <select>
                    <option>[Semua Tipe Transaksi]</option>
                </select>
                <input type="text" placeholder="Cari berdasarkan No Pajak/" style="width: 250px;">
            </div>
        </div>

        <table class="spt-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox"></th>
                    <th>No. Faktur Pajak</th>
                    <th>No Faktur #</th>
                    <th>Tgl Pajak</th>
                    <th>Tipe Transaksi</th>
                    <th>Detail Transaksi</th>
                    <th>DPP</th>
                    <th>PPN</th>
                    <th>Pelanggan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="9" class="empty-state">
                        Belum ada data
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</x-app-layout>
