<x-app-layout>
    @section('title', 'Penyesuaian Stok (Stock Adjustment)')
    @section('subtitle', 'Sesuaikan stok fisik dengan stok sistem akibat hilang, rusak, atau salah catat')

    @if(session('success'))
        <div class="mb-3 bg-green-500/10 border-l-3 border-green-500 p-3 text-green-400 rounded-r-lg text-xs">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Pembuatan Penyesuaian -->
        <div class="lg:col-span-1">
            <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; position: sticky; top: 20px;">
                <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Buat Penyesuaian</h3>
                </div>
                
                <form action="{{ route('client.inventory.adjustments.store') }}" method="POST" style="padding: 14px 18px;">
                    @csrf
                    
                    <div style="margin-bottom: 12px;">
                        <label for="date">Tanggal <span style="color:#f87171">*</span></label>
                        <input type="date" name="date" id="date" value="{{ old('date', date('Y-m-d')) }}" required style="width: 100%;">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label for="product_id">Pilih Barang <span style="color:#f87171">*</span></label>
                        <select name="product_id" id="product_id" required style="width: 100%;">
                            <option value="">-- Pilih Barang --</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }} (Stok: {{ $prod->stock }})</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label for="quantity">Selisih Stok (+ / -) <span style="color:#f87171">*</span></label>
                        <input type="number" name="quantity" id="quantity" value="{{ old('quantity') }}" step="0.01" required style="width: 100%;" placeholder="Gunakan minus (-) untuk mengurangi stok">
                        <small style="color: #64748b; font-size: 0.65rem;">Cth: -5 (hilang 5), 2 (lebih catat 2)</small>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label for="description">Alasan Penyesuaian</label>
                        <textarea name="description" id="description" rows="2" style="width: 100%;" placeholder="Misal: Barang kedaluwarsa atau hilang">{{ old('description') }}</textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                        Simpan Penyesuaian
                    </button>
                </form>
            </div>
        </div>

        <!-- Daftar Penyesuaian -->
        <div class="lg:col-span-2">
            <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
                <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Riwayat Penyesuaian</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Barang</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Selisih</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50">
                            @forelse ($movements as $mov)
                                <tr class="hover:bg-gray-700/20 transition-colors">
                                    <td class="px-6 py-4">
                                        {{ \Carbon\Carbon::parse($mov->date)->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-white">
                                        {{ $mov->product->name ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 font-semibold" style="font-variant-numeric: tabular-nums;">
                                        @if($mov->type == 'in')
                                            <span class="text-green-400">+{{ $mov->quantity }}</span>
                                        @else
                                            <span class="text-red-400">-{{ $mov->quantity }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-400 text-xs">
                                        {{ $mov->notes }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="padding: 40px 14px; text-align: center; color: #64748b;">
                                        <p style="font-size: 0.82rem;">Belum ada riwayat penyesuaian stok.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($movements->hasPages())
                <div class="px-6 py-3 border-t border-gray-700/50">
                    {{ $movements->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
