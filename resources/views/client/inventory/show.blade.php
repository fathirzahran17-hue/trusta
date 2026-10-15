<x-app-layout>
    @section('title', 'Detail Kartu Stok')
    @section('subtitle', 'Riwayat pergerakan stok untuk ' . $product->name)

    <div class="mb-6">
        <a href="{{ route('client.inventory.index') }}" class="inline-flex items-center text-sm text-gray-400 hover:text-white transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Stok
        </a>
    </div>

    <!-- Info Box -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl mb-6 flex flex-col md:flex-row justify-between md:items-center">
        <div>
            <h2 class="text-2xl font-bold text-white font-poppins">{{ $product->name }}</h2>
            <div class="flex items-center gap-4 mt-2 text-sm text-gray-400">
                <span><span class="text-gray-500">SKU:</span> {{ $product->sku }}</span>
                <span><span class="text-gray-500">Unit:</span> {{ $product->unit }}</span>
                <span><span class="text-gray-500">Kategori:</span> {{ $product->category->name ?? '-' }}</span>
            </div>
        </div>
        <div class="mt-4 md:mt-0 bg-gray-800 rounded-xl p-4 min-w-[200px] text-center border border-gray-700">
            <p class="text-xs text-gray-400 font-bold uppercase tracking-wider mb-1">Sisa Stok Akhir</p>
            <p class="text-3xl font-bold text-trusta-teal">{{ $product->stock }}</p>
        </div>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-800 text-gray-400 text-xs uppercase tracking-widest border-b border-gray-700">
                        <th class="p-4 font-medium">Tanggal</th>
                        <th class="p-4 font-medium">Referensi</th>
                        <th class="p-4 font-medium">Keterangan</th>
                        <th class="p-4 font-medium text-center">Masuk (In)</th>
                        <th class="p-4 font-medium text-center">Keluar (Out)</th>
                        <th class="p-4 font-medium text-right">Saldo Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 text-sm">
                    @php $runningStock = 0; @endphp
                    @forelse($movements as $m)
                        @php 
                            if($m->type == 'in') { $runningStock += $m->quantity; }
                            else { $runningStock -= $m->quantity; }
                        @endphp
                        <tr class="hover:bg-gray-800/50 transition-colors group">
                            <td class="p-4 text-gray-300">{{ \Carbon\Carbon::parse($m->date)->format('d/m/Y') }}</td>
                            <td class="p-4">
                                @if($m->reference_type == 'purchase_invoice')
                                    <span class="px-2 py-1 bg-emerald-900/30 text-emerald-400 text-xs rounded font-medium border border-emerald-800/50">Faktur Beli</span>
                                @elseif($m->reference_type == 'sales_invoice')
                                    <span class="px-2 py-1 bg-blue-900/30 text-blue-400 text-xs rounded font-medium border border-blue-800/50">Faktur Jual</span>
                                @else
                                    <span class="text-gray-400">{{ $m->reference_type }}</span>
                                @endif
                                <br>
                                <span class="text-xs text-gray-500 mt-1 block">ID Ref: {{ $m->reference_id }}</span>
                            </td>
                            <td class="p-4 text-gray-400">{{ $m->notes ?? '-' }}</td>
                            <td class="p-4 text-center font-bold text-emerald-400">
                                {{ $m->type == 'in' ? $m->quantity : '-' }}
                            </td>
                            <td class="p-4 text-center font-bold text-red-400">
                                {{ $m->type == 'out' ? $m->quantity : '-' }}
                            </td>
                            <td class="p-4 text-right font-bold text-white tabular-nums">
                                {{ $runningStock }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                Belum ada riwayat stok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
