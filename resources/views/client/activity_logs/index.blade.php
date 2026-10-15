<x-app-layout>
@section('title', 'Log Aktifitas')
@section('subtitle', 'Pusat Pemantauan Aktifitas Sistem')

<style>
    .log-container {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
    }
    .dark .log-container {
        background: var(--trusta-card);
        border-color: var(--trusta-border);
    }

    .log-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .log-table th {
        background: #f8fafc;
        color: #475569;
        text-align: left;
        padding: 12px 16px;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
    }
    .dark .log-table th {
        background: #1e293b;
        color: #94a3b8;
        border-bottom-color: #334155;
    }
    .log-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: top;
    }
    .dark .log-table td {
        border-bottom-color: var(--trusta-border);
        color: #cbd5e1;
    }
    .log-table tr:hover td {
        background: #f8fafc;
    }
    .dark .log-table tr:hover td {
        background: rgba(255,255,255,0.02);
    }
    
    .badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-update { background: #dbeafe; color: #1e40af; }
    .badge-create { background: #dcfce7; color: #166534; }
    .badge-delete { background: #fee2e2; color: #991b1b; }
    
    .dark .badge-update { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .dark .badge-create { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    .dark .badge-delete { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }

    .log-details pre {
        background: #f8fafc;
        padding: 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
        overflow-x: auto;
        margin-top: 8px;
    }
    .dark .log-details pre {
        background: #0f172a;
        border-color: #1e293b;
        color: #94a3b8;
    }
</style>

<div class="log-container">
    <div style="padding: 16px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;" class="dark:border-slate-700">
        <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal);">Catatan Aktifitas (Audit Trail)</h2>
        <div>
            <span style="font-size: 0.85rem; color: #64748b;">Menampilkan 20 aktifitas terakhir</span>
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Waktu</th>
                    <th style="width: 15%;">Pengguna</th>
                    <th style="width: 10%;">Aksi</th>
                    <th style="width: 25%;">Modul & Deskripsi</th>
                    <th style="width: 35%;">Detail Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <div>{{ $log->created_at->format('d/m/Y') }}</div>
                            <div style="font-size: 0.8rem; color: #64748b;">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <div style="font-weight: 500;">{{ $log->user ? $log->user->name : 'Sistem' }}</div>
                            <div style="font-size: 0.8rem; color: #64748b;">IP: {{ $log->ip_address ?? '-' }}</div>
                        </td>
                        <td>
                            @if($log->action === 'create')
                                <span class="badge badge-create">Buat</span>
                            @elseif($log->action === 'update')
                                <span class="badge badge-update">Ubah</span>
                            @elseif($log->action === 'delete')
                                <span class="badge badge-delete">Hapus</span>
                            @else
                                <span class="badge" style="background:#f1f5f9;color:#475569;">{{ $log->action }}</span>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 500;">{{ class_basename($log->model_type) }} #{{ $log->model_id }}</div>
                            <div style="font-size: 0.85rem; margin-top: 4px;">{{ $log->description }}</div>
                        </td>
                        <td class="log-details">
                            @if($log->changes)
                                <div x-data="{ open: false }">
                                    <button @click="open = !open" style="font-size: 0.8rem; color: var(--trusta-primary); background: transparent; border: none; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14" :style="open ? 'transform: rotate(180deg)' : ''" style="transition: transform 0.2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        <span x-text="open ? 'Sembunyikan Detail' : 'Lihat Detail Perubahan'"></span>
                                    </button>
                                    <div x-show="open" style="display: none; margin-top: 8px;">
                                        <pre>{{ json_encode($log->changes, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </div>
                            @else
                                <span style="font-size: 0.85rem; color: #94a3b8;">Tidak ada rincian.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <svg style="margin: 0 auto; height: 48px; width: 48px; color: #cbd5e1; margin-bottom: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <div style="color: #64748b; font-size: 1rem;">Belum ada aktifitas yang tercatat di sistem.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="padding: 16px; border-top: 1px solid #e5e7eb;" class="dark:border-slate-700">
        {{ $logs->links() }}
    </div>
</div>
</x-app-layout>
