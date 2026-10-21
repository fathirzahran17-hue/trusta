<x-app-layout>
    @section('title', 'Jurnal Umum (General Journal)')
    @section('subtitle', 'Pencatatan transaksi akuntansi secara manual')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <form id="filterForm" action="{{ route('client.journals.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Dari:</span>
                <input type="date" name="start_date" value="{{ $startDate ?? \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Sampai:</span>
                <input type="date" name="end_date" value="{{ $endDate ?? \Carbon\Carbon::now()->format('Y-m-d') }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <div class="flex items-center ml-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari (Find)..." class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm w-40 sm:w-48 placeholder-gray-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg text-sm transition-colors">
                Terapkan
            </button>
        </form>

        <div class="flex space-x-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg text-sm transition-colors flex items-center shadow-lg shadow-emerald-900/50">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Excel
            </button>
            <button type="submit" form="filterForm" name="export" value="csv" class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm transition-colors flex items-center shadow-lg shadow-blue-900/50">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                CSV
            </button>
            <button type="submit" form="filterForm" formtarget="_blank" name="export" value="pdf" class="px-3 py-2 bg-gray-800 border border-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg text-sm transition-colors flex items-center shadow-lg">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                PDF
            </button>
            <a href="{{ route('client.journals.create') }}" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center ml-2">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Buat Jurnal Baru
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg">
            {{ session('error') }}
        </div>
    @endif
    @if(session('success'))
        <div class="mb-4 bg-green-500/10 border-l-4 border-green-500 p-4 text-green-400 rounded-r-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-gray-800/40 border border-gray-700/50 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                    <tr>
                        <th scope="col" class="px-6 py-4 rounded-tl-xl font-semibold">Tgl Transaksi</th>
                        <th scope="col" class="px-6 py-4 font-semibold">No. Ref</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Keterangan</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Total Debit/Kredit</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-center">Status</th>
                        <th scope="col" class="px-6 py-4 text-center rounded-tr-xl font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($journals as $journal)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 text-white">
                                {{ $journal->transaction_date->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 font-medium text-trusta-teal">
                                <a href="{{ route('client.journals.show', $journal->id) }}" class="hover:underline">
                                    {{ $journal->ref_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                {{ Str::limit($journal->description, 50) }}
                                <div class="text-xs text-gray-500 mt-1">Oleh: {{ $journal->createdBy->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-white">
                                Rp {{ number_format($journal->total_debit, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($journal->status === 'posted')
                                    <span class="bg-green-500/10 text-green-400 border border-green-500/20 text-[10px] px-2 py-0.5 rounded uppercase">Posted</span>
                                @elseif($journal->status === 'void')
                                    <span class="bg-red-500/10 text-red-400 border border-red-500/20 text-[10px] px-2 py-0.5 rounded uppercase">Void</span>
                                @else
                                    <span class="bg-gray-500/10 text-gray-400 border border-gray-500/20 text-[10px] px-2 py-0.5 rounded uppercase">{{ $journal->status }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-3">
                                    <a href="{{ route('client.journals.show', $journal->id) }}" class="text-gray-400 hover:text-white transition-colors" title="Lihat Detail">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    
                                    <form action="{{ route('client.journals.destroy', $journal->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus (void) jurnal ini? Tindakan ini akan membalikkan saldo akun terkait.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-400 transition-colors" title="Hapus Jurnal">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p>Belum ada catatan jurnal. Silakan buat entri baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4">
        {{ $journals->links() }}
    </div>
</x-app-layout>
