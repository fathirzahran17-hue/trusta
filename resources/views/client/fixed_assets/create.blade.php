<x-app-layout>
    @section('title', 'Tambah Aset Tetap Baru')
    @section('subtitle', 'Catat data aset tetap baru yang dimiliki perusahaan')

    <div class="mb-6">
        <a href="{{ route('client.fixed_assets.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Aset Tetap
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-4xl">
        <form action="{{ route('client.fixed_assets.store') }}" method="POST">
            @csrf
            
            <div class="p-8 space-y-6">
                <h3 class="text-lg font-medium text-white mb-4 border-b border-gray-700 pb-2">Informasi Dasar Aset</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-300 mb-2">Kode Aset <span class="text-red-400">*</span></label>
                        <input type="text" name="code" id="code" value="{{ old('code', $nextCode) }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="FA-001">
                        @error('code')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nama Aset <span class="text-red-400">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: Laptop ASUS VivoBook">
                        @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="fixed_asset_category_id" class="block text-sm font-medium text-gray-300 mb-2">Kategori Aset</label>
                        <select name="fixed_asset_category_id" id="fixed_asset_category_id"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('fixed_asset_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="acquisition_date" class="block text-sm font-medium text-gray-300 mb-2">Tanggal Perolehan <span class="text-red-400">*</span></label>
                        <input type="date" name="acquisition_date" id="acquisition_date" value="{{ old('acquisition_date', date('Y-m-d')) }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('acquisition_date')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <h3 class="text-lg font-medium text-white mb-4 mt-8 border-b border-gray-700 pb-2">Nilai & Penyusutan</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="acquisition_cost" class="block text-sm font-medium text-gray-300 mb-2">Harga Perolehan (Rp) <span class="text-red-400">*</span></label>
                        <input type="number" step="0.01" name="acquisition_cost" id="acquisition_cost" value="{{ old('acquisition_cost', 0) }}" required min="0"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('acquisition_cost')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="useful_life_months" class="block text-sm font-medium text-gray-300 mb-2">Umur Manfaat (Bulan) <span class="text-red-400">*</span></label>
                        <input type="number" name="useful_life_months" id="useful_life_months" value="{{ old('useful_life_months', 48) }}" required min="1"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('useful_life_months')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="salvage_value" class="block text-sm font-medium text-gray-300 mb-2">Nilai Sisa / Residu (Rp)</label>
                        <input type="number" step="0.01" name="salvage_value" id="salvage_value" value="{{ old('salvage_value', 0) }}" min="0"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="depreciation_method" class="block text-sm font-medium text-gray-300 mb-2">Metode Penyusutan <span class="text-red-400">*</span></label>
                        <select name="depreciation_method" id="depreciation_method" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="straight_line" {{ old('depreciation_method') == 'straight_line' ? 'selected' : '' }}>Garis Lurus (Straight Line)</option>
                            <option value="declining_balance" {{ old('depreciation_method') == 'declining_balance' ? 'selected' : '' }}>Saldo Menurun (Declining Balance)</option>
                        </select>
                    </div>
                    <div>
                        <label for="condition" class="block text-sm font-medium text-gray-300 mb-2">Kondisi Aset <span class="text-red-400">*</span></label>
                        <select name="condition" id="condition" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="baik" {{ old('condition') == 'baik' ? 'selected' : '' }}>Baik</option>
                            <option value="rusak_ringan" {{ old('condition') == 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                            <option value="rusak_berat" {{ old('condition') == 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                        </select>
                    </div>
                </div>

                <h3 class="text-lg font-medium text-white mb-4 mt-8 border-b border-gray-700 pb-2">Informasi Tambahan</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Lokasi Aset</label>
                        <input type="text" name="location" id="location" value="{{ old('location') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: Kantor Pusat, Gudang A">
                    </div>
                    <div>
                        <label for="brand" class="block text-sm font-medium text-gray-300 mb-2">Merek / Brand</label>
                        <input type="text" name="brand" id="brand" value="{{ old('brand') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: Toyota, ASUS, Samsung">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="serial_number" class="block text-sm font-medium text-gray-300 mb-2">Nomor Seri</label>
                        <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Nomor seri unik alat">
                    </div>
                    <div>
                        <label for="invoice_number" class="block text-sm font-medium text-gray-300 mb-2">No. Faktur Pembelian</label>
                        <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Nomor faktur pembelian aset">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="supplier_name" class="block text-sm font-medium text-gray-300 mb-2">Nama Pemasok</label>
                        <input type="text" name="supplier_name" id="supplier_name" value="{{ old('supplier_name') }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Pemasok / toko pembelian">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Deskripsi</label>
                        <textarea name="description" id="description" rows="2"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Deskripsi singkat aset...">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Catatan Internal</label>
                    <textarea name="notes" id="notes" rows="2"
                        class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                        placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="px-8 py-5 bg-[#0F172A] border-t border-gray-800 flex justify-end space-x-3">
                <a href="{{ route('client.fixed_assets.index') }}" class="px-5 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg text-sm transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2.5 bg-trusta-teal hover:bg-teal-400 text-[#0F172A] font-semibold rounded-lg text-sm transition-colors shadow-lg shadow-teal-900/50">
                    Simpan Aset Tetap
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
