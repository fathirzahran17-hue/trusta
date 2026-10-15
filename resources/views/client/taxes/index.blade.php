<x-app-layout>
    @section('title', 'Tarif Pajak')
    @section('subtitle', 'Kelola daftar tarif pajak dan pemetaan akun akuntansinya')

    <div class="flex justify-between items-center mb-6">
        <div></div>
        <a href="{{ route('client.taxes.create') }}" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Tarif Pajak
        </a>
    </div>

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
                        <th scope="col" class="px-6 py-4 rounded-tl-xl font-semibold">Nama Pajak</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Tarif (%)</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Akun Pajak Penjualan (Keluaran)</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Akun Pajak Pembelian (Masukan)</th>
                        <th scope="col" class="px-6 py-4 text-center rounded-tr-xl font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @forelse ($taxes as $tax)
                        <tr class="hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 font-medium text-white">
                                {{ $tax->name }}
                                @if(!$tax->is_active)
                                    <span class="ml-2 bg-red-500/10 text-red-400 border border-red-500/20 text-[10px] px-2 py-0.5 rounded">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-trusta-teal">
                                {{ number_format($tax->rate, 2) }}%
                            </td>
                            <td class="px-6 py-4 text-gray-400">
                                {{ $tax->salesTaxAccount->name ?? '-' }}
                                <div class="text-xs text-gray-500">{{ $tax->salesTaxAccount->code ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-400">
                                {{ $tax->purchaseTaxAccount->name ?? '-' }}
                                <div class="text-xs text-gray-500">{{ $tax->purchaseTaxAccount->code ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-3">
                                    <a href="{{ route('client.taxes.edit', $tax->id) }}" class="text-gray-400 hover:text-white transition-colors" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    
                                    <form action="{{ route('client.taxes.destroy', $tax->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pajak ini?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-400 transition-colors" title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                                <p>Belum ada tarif pajak yang dikonfigurasi. Silakan tambah baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
