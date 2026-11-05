<x-app-layout>
    @section('title', 'Tambah Kategori Produk')
    @section('subtitle', 'Tambahkan kategori baru untuk mengelompokkan barang/jasa Anda')

    <div class="mb-6">
        <a href="{{ route('client.inventory.categories') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Kategori
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-2xl">
        <form action="{{ route('client.inventory.categories.store') }}" method="POST">
            @csrf
            
            <div class="doc-section doc-stack">
                <div>
                    <label for="name" class="block">Nama Kategori <span class="req">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        placeholder="Contoh: Elektronik, Pakaian, Jasa Konsultasi">
                    @error('name')
                        <p class="field-msg">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="description" class="block">Deskripsi Kategori (Opsional)</label>
                    <textarea name="description" id="description" rows="3"
                        placeholder="Keterangan singkat tentang kategori ini...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="field-msg">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="doc-actions doc-actions-end">
                <a href="{{ route('client.inventory.categories') }}" class="btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
