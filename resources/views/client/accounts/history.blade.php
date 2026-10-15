<x-app-layout>
@section('title', 'Histori Akun')
@section('subtitle', 'Buku Besar - Histori Mutasi per Akun Perkiraan')

<style>
    .ledger-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        background: #fff;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        gap: 16px;
    }
    .dark .ledger-toolbar {
        background: var(--trusta-card);
        border-color: var(--trusta-border);
    }
    
    .ledger-select-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .ledger-select {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.95rem;
        background: #fff;
        color: #374151;
        width: 100%;
        max-width: 400px;
    }
    .dark .ledger-select {
        background: var(--trusta-dark);
        border-color: var(--trusta-border);
        color: #f3f4f6;
    }
    
    .btn-search {
        background: var(--trusta-primary);
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .ledger-table-wrapper {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        overflow-x: auto;
    }
    .dark .ledger-table-wrapper {
        background: var(--trusta-card);
        border-color: var(--trusta-border);
    }
    
    .ledger-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    
    .ledger-table th {
        background: #475569;
        color: #f8fafc;
        text-align: left;
        padding: 12px 16px;
        font-weight: 500;
    }
    .dark .ledger-table th {
        background: #1e293b;
        color: #e2e8f0;
    }
    
    .ledger-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: top;
    }
    .dark .ledger-table td {
        border-bottom-color: var(--trusta-border);
        color: #d1d5db;
    }
    
    .ledger-table tr:hover td {
        background: #f8fafc;
    }
    .dark .ledger-table tr:hover td {
        background: rgba(255,255,255,0.02);
    }
    
    .text-right { text-align: right !important; }
</style>

<div class="ledger-toolbar">
    <form action="{{ route('client.accounts.history') }}" method="GET" class="ledger-select-wrapper" style="width: 100%; display: flex; align-items: center;">
        <label style="font-weight: 600; color: var(--trusta-text-primary); white-space: nowrap;">Pilih Akun:</label>
        <select name="account" class="ledger-select" required onchange="this.form.action='{{ url('client/accounts/history') }}/' + this.value">
            <option value="">-- Pilih Akun Perkiraan --</option>
            @foreach($accounts as $acc)
                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ ($account && $account->id == $acc->id) ? 'selected' : '' }}>
                    {{ $acc->code }} - {{ $acc->name }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn-search">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            Tampilkan Histori
        </button>
    </form>
</div>

@if($account)
    <div style="margin-bottom: 16px; padding: 16px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-primary); margin-bottom: 4px;">{{ $account->code }} - {{ $account->name }}</h3>
            <div style="font-size: 0.9rem; color: var(--trusta-text-muted);">Tipe Akun: {{ ucfirst(str_replace('_', ' ', $account->type)) }} | Saldo Normal: {{ ucfirst($account->normal_balance) }}</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.85rem; color: var(--trusta-text-muted); margin-bottom: 4px;">Saldo Berjalan (Perkiraan)</div>
            @php
                $totalDebit = $mutations->sum('debit');
                $totalCredit = $mutations->sum('credit');
                $balance = $account->normal_balance === 'debit' ? ($account->opening_balance + $totalDebit - $totalCredit) : ($account->opening_balance + $totalCredit - $totalDebit);
            @endphp
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--trusta-text-primary);">Rp {{ number_format($balance, 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="ledger-table-wrapper">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 15%;">No. Bukti / Jurnal</th>
                    <th style="width: 33%;">Keterangan</th>
                    <th class="text-right" style="width: 20%;">Debit</th>
                    <th class="text-right" style="width: 20%;">Kredit</th>
                </tr>
            </thead>
            <tbody>
                <!-- Saldo Awal -->
                <tr style="background: rgba(0,0,0,0.02);">
                    <td>-</td>
                    <td>-</td>
                    <td style="font-style: italic;">Saldo Awal</td>
                    <td class="text-right">{{ $account->normal_balance === 'debit' && $account->opening_balance > 0 ? 'Rp ' . number_format($account->opening_balance, 2, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $account->normal_balance === 'credit' && $account->opening_balance > 0 ? 'Rp ' . number_format($account->opening_balance, 2, ',', '.') : '-' }}</td>
                </tr>

                @forelse($mutations as $mutation)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($mutation->journal->transaction_date)->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('client.journals.show', $mutation->journal->id) }}" style="color: var(--trusta-primary); text-decoration: none; font-weight: 500;">
                                {{ $mutation->journal->ref_number }}
                            </a>
                        </td>
                        <td>{{ $mutation->description ?: $mutation->journal->description }}</td>
                        <td class="text-right">{{ $mutation->debit > 0 ? 'Rp ' . number_format($mutation->debit, 2, ',', '.') : '-' }}</td>
                        <td class="text-right">{{ $mutation->credit > 0 ? 'Rp ' . number_format($mutation->credit, 2, ',', '.') : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <svg style="margin: 0 auto; height: 48px; width: 48px; color: #cbd5e1; margin-bottom: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <div style="color: #64748b; font-size: 1rem;">Belum ada transaksi pada akun ini.</div>
                        </td>
                    </tr>
                @endforelse
                
                <!-- Total Mutasi -->
                @if($mutations->count() > 0)
                <tr style="background: rgba(0,0,0,0.02); font-weight: 600;">
                    <td colspan="3" class="text-right">Total Mutasi:</td>
                    <td class="text-right">Rp {{ number_format($totalDebit, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($totalCredit, 2, ',', '.') }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
@else
    <div class="ledger-table-wrapper" style="padding: 60px 20px; text-align: center; border: 1px dashed #cbd5e1;">
        <svg style="margin: 0 auto; height: 64px; width: 64px; color: #cbd5e1; margin-bottom: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
        <h3 style="font-size: 1.25rem; font-weight: 600; color: #334155; margin-bottom: 8px;" class="dark:text-slate-300">Pilih Akun Terlebih Dahulu</h3>
        <p style="color: #64748b; max-width: 400px; margin: 0 auto;" class="dark:text-slate-400">Silakan pilih akun dari dropdown di atas untuk melihat seluruh histori transaksinya.</p>
    </div>
@endif

</x-app-layout>
