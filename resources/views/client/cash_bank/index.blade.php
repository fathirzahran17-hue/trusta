<x-app-layout>
    @section('title', 'Kas & Bank')
    @section('subtitle', 'Pusat pengelolaan uang tunai dan rekening bank perusahaan')

    @if(session('success'))
        <div class="mb-3 bg-green-500/10 border-l-3 border-green-500 p-3 text-green-400 rounded-r-lg text-xs">
            {{ session('success') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px;">
        @forelse($accounts as $account)
            <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 16px; transition: all 0.2s; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='rgba(20,184,166,0.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.06)'">
                <div style="position: absolute; top: -4px; right: -4px; opacity: 0.06;">
                    <svg style="width: 48px; height: 48px; color: var(--trusta-teal);" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.64-2.25 1.64-1.74 0-2.26-.95-2.32-1.81H7.84c.05 1.67 1.07 2.84 2.89 3.24V19h2.33v-1.7c1.78-.34 2.94-1.37 2.94-3.01-.01-1.82-1.47-2.61-3.69-3.15z"/></svg>
                </div>
                
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 2px;">{{ $account->name }}</h3>
                <p style="font-size: 0.7rem; font-family: monospace; color: #64748b; margin-bottom: 14px;">{{ $account->code }}</p>
                
                <div style="margin-bottom: 14px;">
                    <p style="font-size: 0.6rem; color: #475569; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px;">Saldo Tersedia</p>
                    <p style="font-size: 1.4rem; font-weight: 700; color: {{ $account->balance < 0 ? '#ef4444' : '#fff' }}; font-variant-numeric: tabular-nums;">Rp {{ number_format($account->balance, 2, ',', '.') }}</p>
                </div>
                
                <a href="{{ route('client.cash-bank.show', $account->id) }}" class="btn-primary" style="width: 100%; justify-content: center; font-size: 0.75rem;">
                    Kelola Akun Ini
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
        @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px 16px; background: rgba(30,41,59,0.3); border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);">
                <svg style="width: 40px; height: 40px; margin: 0 auto 12px; color: #334155;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3 style="font-size: 0.95rem; font-weight: 600; color: #fff; margin-bottom: 4px;">Belum ada Akun Kas/Bank</h3>
                <p style="color: #64748b; font-size: 0.8rem; margin-bottom: 14px;">Silakan buat akun Kas atau Bank di Master Data Chart of Accounts.</p>
                <a href="{{ route('client.accounts.create') }}" class="btn-primary" style="display: inline-flex;">Buat Akun Sekarang</a>
            </div>
        @endforelse
    </div>
</x-app-layout>
