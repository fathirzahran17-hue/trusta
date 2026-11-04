<x-app-layout>
    @section('title', 'Tambah Pemasok Baru')
    @section('subtitle', 'Tambahkan data pemasok/vendor untuk keperluan faktur pembelian')

    <div class="mb-6">
        <a href="{{ route('client.suppliers.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pemasok
        </a>
    </div>

    <div class="doc-card doc-card-wide">
        <form action="{{ route('client.suppliers.store') }}" method="POST">
            @csrf
            
            <div class="doc-section doc-stack">
                <h3 class="form-heading">Informasi Dasar</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block">Nama Pemasok / Perusahaan <span class="req">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            placeholder="Contoh: CV XYZ atau Bpk. Budi">
                        @error('name')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                            placeholder="email@contoh.com">
                        @error('email')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="phone" class="block">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                            placeholder="08123456789">
                        @error('phone')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <h3 class="form-heading">Informasi Tambahan</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="address" class="block">Alamat Toko</label>
                        <textarea name="address" id="address" rows="2"
                            placeholder="Alamat toko atau lokasi utama pemasok...">{{ old('address') }}</textarea>
                        @error('address')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="tax_address" class="block">Alamat NPWP</label>
                        <textarea name="tax_address" id="tax_address" rows="2"
                            placeholder="Alamat penagihan pajak / NPWP...">{{ old('tax_address') }}</textarea>
                        @error('tax_address')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="npwp" class="block">NPWP</label>
                        <input type="text" name="npwp" id="npwp" value="{{ old('npwp') }}"
                            placeholder="Nomor Pokok Wajib Pajak">
                        @error('npwp')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="default_term_days" class="block">Jangka Waktu Pembayaran (Hari)</label>
                        <input type="number" name="default_term_days" id="default_term_days" value="{{ old('default_term_days', 30) }}" min="0"
                            placeholder="30">
                        <p class="field-hint">Default jatuh tempo invoice (misal: 30 hari dari tanggal faktur).</p>
                        @error('default_term_days')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="flex items-center pt-4">
                        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                            class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                            Status Aktif
                            <p class="text-xs text-gray-500 font-normal">Jika tidak aktif, tidak akan muncul di opsi pembuatan invoice baru.</p>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="doc-actions doc-actions-end">
                <a href="{{ route('client.suppliers.index') }}" class="btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Pemasok
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
