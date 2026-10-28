<x-app-layout>
    @section('title', 'Uang Muka Pembelian')
    @section('subtitle', 'Pembayaran uang muka ke pemasok')

    <div style="display: flex; justify-content: flex-end; margin-bottom: 14px;">
        <a href="{{ route('client.purchases.down_payments.create') }}" class="btn-primary">
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Bayar Uang Muka
        </a>
    </div>

    @if(session('success'))
        <div class="mb-3 bg-green-500/10 border-l-3 border-green-500 p-3 text-green-400 rounded-r-lg text-xs">
            {{ session('success') }}
        </div>
    @endif

    <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No. Transaksi</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Pemasok</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tgl Bayar</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Terkait PO</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Jumlah (Rp)</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Terpakai</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Sisa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($downPayments as $dp)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $dp->payment_number }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-white">{{ $dp->supplier->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ $dp->payment_date->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $dp->purchaseOrder->po_number ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-trusta-teal" style="font-variant-numeric: tabular-nums;">
                                Rp {{ number_format($dp->amount, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right" style="font-variant-numeric: tabular-nums;">Rp {{ number_format($dp->applied_amount, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right font-semibold" style="font-variant-numeric: tabular-nums;">Rp {{ number_format($dp->remaining, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 40px 14px; text-align: center; color: #64748b;">
                                <svg style="width: 36px; height: 36px; margin: 0 auto 10px; color: #334155;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p style="font-size: 0.82rem;">Belum ada uang muka pembelian. Silakan buat baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($downPayments->hasPages())
        <div class="px-6 py-3 border-t border-gray-700/50">
            {{ $downPayments->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
