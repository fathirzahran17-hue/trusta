<x-app-layout>
    @section('title', 'Edit Aset Tetap')
    @section('subtitle', 'Ubah data aset tetap: ' . $fixedAsset->name)

    <div class="mb-6">
        <a href="{{ route('client.fixed_assets.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Aset Tetap
        </a>
    </div>

    {{-- Info Ringkasan Depresiasi --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 max-w-4xl">
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase mb-1">Harga Perolehan</p>
            <p class="text-lg font-bold text-white tabular-nums">Rp {{ \App\Support\Money::fmt($fixedAsset->acquisition_cost) }}</p>
        </div>
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase mb-1">Akum. Penyusutan</p>
            <p class="text-lg font-bold text-red-400 tabular-nums">Rp {{ \App\Support\Money::fmt($fixedAsset->accumulated_depreciation) }}</p>
        </div>
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase mb-1">Nilai Buku</p>
            <p class="text-lg font-bold text-trusta-teal tabular-nums">Rp {{ \App\Support\Money::fmt($fixedAsset->book_value) }}</p>
        </div>
        <div class="bg-[#1E293B] border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-400 uppercase mb-1">Penyusutan/Bulan</p>
            <p class="text-lg font-bold text-yellow-400 tabular-nums">Rp {{ \App\Support\Money::fmt($fixedAsset->monthly_depreciation) }}</p>
        </div>
    </div>

    <div class="doc-card doc-card-wide">
        <form action="{{ route('client.fixed_assets.update', $fixedAsset) }}" method="POST">
            @csrf @method('PUT')
            
            <div class="doc-section doc-stack">
                <h3 class="form-heading">Informasi Dasar Aset</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="code" class="block">Kode Aset <span class="req">*</span></label>
                        <input type="text" name="code" id="code" value="{{ old('code', $fixedAsset->code) }}" required>
                        @error('code')<p class="field-msg">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="block">Nama Aset <span class="req">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $fixedAsset->name) }}" required>
                        @error('name')<p class="field-msg">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="fixed_asset_category_id" class="block">Kategori</label>
                        <select name="fixed_asset_category_id" id="fixed_asset_category_id">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('fixed_asset_category_id', $fixedAsset->fixed_asset_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="acquisition_date" class="block">Tanggal Perolehan <span class="req">*</span></label>
                        <input type="date" name="acquisition_date" id="acquisition_date" value="{{ old('acquisition_date', $fixedAsset->acquisition_date->format('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label for="status" class="block">Status <span class="req">*</span></label>
                        <select name="status" id="status" required>
                            <option value="aktif" {{ old('status', $fixedAsset->status) == 'aktif' ? 'selected' : '' }}>Aktif</option>
                            <option value="dijual" {{ old('status', $fixedAsset->status) == 'dijual' ? 'selected' : '' }}>Dijual</option>
                            <option value="dihapus" {{ old('status', $fixedAsset->status) == 'dihapus' ? 'selected' : '' }}>Dihapus (Write-Off)</option>
                            <option value="arsip" {{ old('status', $fixedAsset->status) == 'arsip' ? 'selected' : '' }}>Arsip</option>
                        </select>
                    </div>
                </div>

                <h3 class="form-heading">Nilai & Penyusutan</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="acquisition_cost" class="block">Harga Perolehan (Rp) <span class="req">*</span></label>
                        <input type="number" step="0.01" name="acquisition_cost" id="acquisition_cost" value="{{ old('acquisition_cost', $fixedAsset->acquisition_cost) }}" required min="0">
                    </div>
                    <div>
                        <label for="useful_life_months" class="block">Umur Manfaat (Bulan) <span class="req">*</span></label>
                        <input type="number" name="useful_life_months" id="useful_life_months" value="{{ old('useful_life_months', $fixedAsset->useful_life_months) }}" required min="1">
                    </div>
                    <div>
                        <label for="salvage_value" class="block">Nilai Sisa / Residu (Rp)</label>
                        <input type="number" step="0.01" name="salvage_value" id="salvage_value" value="{{ old('salvage_value', $fixedAsset->salvage_value) }}" min="0">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="depreciation_method" class="block">Metode Penyusutan <span class="req">*</span></label>
                        <select name="depreciation_method" id="depreciation_method" required>
                            <option value="straight_line" {{ old('depreciation_method', $fixedAsset->depreciation_method) == 'straight_line' ? 'selected' : '' }}>Garis Lurus (Straight Line)</option>
                            <option value="declining_balance" {{ old('depreciation_method', $fixedAsset->depreciation_method) == 'declining_balance' ? 'selected' : '' }}>Saldo Menurun (Declining Balance)</option>
                        </select>
                    </div>
                    <div>
                        <label for="condition" class="block">Kondisi Aset <span class="req">*</span></label>
                        <select name="condition" id="condition" required>
                            <option value="baik" {{ old('condition', $fixedAsset->condition) == 'baik' ? 'selected' : '' }}>Baik</option>
                            <option value="rusak_ringan" {{ old('condition', $fixedAsset->condition) == 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                            <option value="rusak_berat" {{ old('condition', $fixedAsset->condition) == 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                        </select>
                    </div>
                </div>

                @include('client.fixed_assets._acquisition')

                <h3 class="form-heading">Informasi Tambahan</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="location" class="block">Lokasi Aset</label>
                        <input type="text" name="location" id="location" value="{{ old('location', $fixedAsset->location) }}">
                    </div>
                    <div>
                        <label for="brand" class="block">Merek / Brand</label>
                        <input type="text" name="brand" id="brand" value="{{ old('brand', $fixedAsset->brand) }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="serial_number" class="block">Nomor Seri</label>
                        <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number', $fixedAsset->serial_number) }}">
                    </div>
                    <div>
                        <label for="invoice_number" class="block">No. Faktur Pembelian</label>
                        <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $fixedAsset->invoice_number) }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="supplier_name" class="block">Nama Pemasok</label>
                        <input type="text" name="supplier_name" id="supplier_name" value="{{ old('supplier_name', $fixedAsset->supplier_name) }}">
                    </div>
                    <div>
                        <label for="description" class="block">Deskripsi</label>
                        <textarea name="description" id="description" rows="2">{{ old('description', $fixedAsset->description) }}</textarea>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block">Catatan Internal</label>
                    <textarea name="notes" id="notes" rows="2">{{ old('notes', $fixedAsset->notes) }}</textarea>
                </div>
            </div>

            <div class="doc-actions doc-actions-end">
                <a href="{{ route('client.fixed_assets.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">
                    Perbarui Aset Tetap
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
