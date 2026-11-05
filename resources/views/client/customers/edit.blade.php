<x-app-layout>
    @section('title', 'Ubah Data Pelanggan')
    @section('subtitle', 'Edit data kontak pelanggan: ' . $customer->name)

    <div class="mb-6">
        <a href="{{ route('client.customers.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pelanggan
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-4xl">
        <form action="{{ route('client.customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="doc-section doc-stack">
                <h3 class="form-heading">Informasi Dasar</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block">Nama Pelanggan / Perusahaan <span class="req">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required>
                        @error('name')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="company_name" class="block">Perusahaan / Instansi</label>
                        <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $customer->company_name ?? '') }}" maxlength="255" placeholder="Boleh dikosongkan bila perorangan">
                        @error('company_name')<p class="field-msg">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="block">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}">
                        @error('email')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="phone" class="block">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $customer->phone) }}">
                        @error('phone')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <h3 class="form-heading">Informasi Tambahan</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="address" class="block">Alamat Lengkap</label>
                        <textarea name="address" id="address" rows="3">{{ old('address', $customer->address) }}</textarea>
                        @error('address')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="npwp" class="block">NPWP</label>
                        <input type="text" name="npwp" id="npwp" value="{{ old('npwp', $customer->npwp) }}">
                        @error('npwp')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="customer_category_id" class="block">Kategori Pelanggan</label>
                        <select name="customer_category_id" id="customer_category_id">
                            <option value="">Tanpa kategori</option>
                            @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(old('customer_category_id', $customer->customer_category_id ?? null) == $cat->id)>{{ $cat->name }}</option>@endforeach
                        </select>
                        @if($categories->isEmpty())<p class="field-hint">Belum ada kategori. Buat di menu Kategori Pelanggan.</p>@endif
                        @error('customer_category_id')<p class="field-msg">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="default_term_days" class="block">Jangka Waktu Pembayaran (Hari)</label>
                        <input type="number" name="default_term_days" id="default_term_days" value="{{ old('default_term_days', $customer->default_term_days ?? 30) }}" min="0">
                        @error('default_term_days')
                            <p class="field-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="flex items-center pt-4">
                        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $customer->is_active) ? 'checked' : '' }}
                            class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                            Status Aktif
                        </label>
                    </div>

                    @if($attrLabel = auth()->user()->company?->custom_customer_attr_1)
                    <div>
                        <label for="custom_attr_1" class="block">{{ $attrLabel }}</label>
                        <input type="text" name="custom_attr_1" id="custom_attr_1" value="{{ old('custom_attr_1', $customer->custom_attr_1) }}" maxlength="255">
                    </div>
                    @endif

                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="doc-actions doc-actions-end">
                <a href="{{ route('client.customers.index') }}" class="btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
