<x-app-layout>
    @section('title', 'Tambah Kategori Produk')
    @section('subtitle', 'Tambahkan kategori baru untuk mengelompokkan barang/jasa Anda')

    <div class="mb-6">
        <a href="{{ route('client.categories.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Kategori
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-2xl">
        <form action="{{ route('client.categories.store') }}" method="POST">
            @csrf
            
            <div class="p-8 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nama Kategori <span class="text-red-400">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                        placeholder="Contoh: Elektronik, Pakaian, Jasa Konsultasi">
                    @error('name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Kategori (Opsional)</label>
                    <textarea name="description" id="description" rows="3"
                        class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                        placeholder="Keterangan singkat tentang kategori ini...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-8 py-4 bg-gray-800/50 border-t border-gray-700/50 flex justify-end space-x-3">
                <a href="{{ route('client.categories.index') }}" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
