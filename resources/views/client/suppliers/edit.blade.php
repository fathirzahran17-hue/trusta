<x-app-layout>
    @section('title', 'Ubah Data Pemasok')
    @section('subtitle', 'Edit data kontak pemasok: ' . $supplier->name)

    <div class="mb-6">
        <a href="{{ route('client.suppliers.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pemasok
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-4xl">
        <form action="{{ route('client.suppliers.update', $supplier->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="p-8 space-y-6">
                <h3 class="text-lg font-medium text-white mb-4 border-b border-gray-700 pb-2">Informasi Dasar</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nama Pemasok / Perusahaan <span class="text-red-400">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $supplier->name) }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('name')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $supplier->email) }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('email')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $supplier->phone) }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <h3 class="text-lg font-medium text-white mb-4 mt-8 border-b border-gray-700 pb-2">Informasi Tambahan</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-300 mb-2">Alamat Toko</label>
                        <textarea name="address" id="address" rows="2"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">{{ old('address', $supplier->address) }}</textarea>
                        @error('address')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="tax_address" class="block text-sm font-medium text-gray-300 mb-2">Alamat NPWP</label>
                        <textarea name="tax_address" id="tax_address" rows="2"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">{{ old('tax_address', $supplier->tax_address) }}</textarea>
                        @error('tax_address')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="npwp" class="block text-sm font-medium text-gray-300 mb-2">NPWP</label>
                        <input type="text" name="npwp" id="npwp" value="{{ old('npwp', $supplier->npwp) }}"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('npwp')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="default_term_days" class="block text-sm font-medium text-gray-300 mb-2">Jangka Waktu Pembayaran (Hari)</label>
                        <input type="number" name="default_term_days" id="default_term_days" value="{{ old('default_term_days', $supplier->default_term_days ?? 30) }}" min="0"
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        @error('default_term_days')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="flex items-center pt-4">
                        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}
                            class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                            Status Aktif
                        </label>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-8 py-4 bg-gray-800/50 border-t border-gray-700/50 flex justify-end space-x-3">
                <a href="{{ route('client.suppliers.index') }}" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
