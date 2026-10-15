<x-app-layout>
    @section('title', 'Transfer Antar Gudang')
    @section('subtitle', 'Pindahkan stok barang dari satu lokasi gudang ke lokasi lain')

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
        <!-- Form Pembuatan Transfer -->
        <div class="lg:col-span-1">
            <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; position: sticky; top: 20px;">
                <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Buat Transfer Barang</h3>
                </div>
                
                <form action="{{ route('client.inventory.transfers.store') }}" method="POST" style="padding: 14px 18px;">
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
                                <option value="{{ $prod->id }}">{{ $prod->code ? $prod->code . ' - ' : '' }}{{ $prod->name }} (Total Stok: {{ $prod->stock }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                        <div>
                            <label for="from_warehouse">Gudang Asal <span style="color:#f87171">*</span></label>
                            <select name="from_warehouse" id="from_warehouse" required style="width: 100%;">
                                <option value="">-- Pilih --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="to_warehouse">Gudang Tujuan <span style="color:#f87171">*</span></label>
                            <select name="to_warehouse" id="to_warehouse" required style="width: 100%;">
                                <option value="">-- Pilih --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label for="quantity">Jumlah Dipindahkan <span style="color:#f87171">*</span></label>
                        <input type="number" name="quantity" id="quantity" value="{{ old('quantity', 1) }}" min="1" step="0.01" required style="width: 100%;">
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label for="description">Keterangan Transfer</label>
                        <textarea name="description" id="description" rows="2" style="width: 100%;" placeholder="Catatan opsional...">{{ old('description') }}</textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; background: rgba(59,130,246,0.2); color: #60a5fa; border-color: rgba(59,130,246,0.3);">
                        Proses Transfer
                    </button>
                </form>
            </div>
        </div>

        <!-- Daftar Transfer -->
        <div class="lg:col-span-2">
            <div style="background: rgba(30,41,59,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden;">
                <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Riwayat Mutasi Antar Gudang</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Barang</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Tipe</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Jumlah</th>
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
                                    <td class="px-6 py-4">
                                        @if($mov->type == 'in')
                                            <span class="bg-green-500/20 text-green-400 px-2 py-1 rounded text-xs">MASUK</span>
                                        @else
                                            <span class="bg-red-500/20 text-red-400 px-2 py-1 rounded text-xs">KELUAR</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 font-semibold" style="font-variant-numeric: tabular-nums;">
                                        {{ $mov->quantity }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-400 text-xs">
                                        {{ $mov->notes }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 40px 14px; text-align: center; color: #64748b;">
                                        <p style="font-size: 0.82rem;">Belum ada riwayat transfer barang antar gudang.</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('form').addEventListener('submit', function(e) {
                const fromWH = document.getElementById('from_warehouse').value;
                const toWH = document.getElementById('to_warehouse').value;
                
                if (fromWH && toWH && fromWH === toWH) {
                    e.preventDefault();
                    alert('Gudang asal dan tujuan tidak boleh sama!');
                }
            });
        });
    </script>
</x-app-layout>
