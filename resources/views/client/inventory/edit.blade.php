<x-app-layout>
    @section('title', 'Edit Barang / Jasa')
    @section('subtitle', 'Ubah data: ' . $product->name)

    <div class="mb-6">
        <a href="{{ route('client.inventory.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar
        </a>
    </div>

    <form action="{{ route('client.inventory.update', $product) }}" method="POST">
        @csrf @method('PUT')
        <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-300 mb-1">Kode / SKU <span class="text-red-400">*</span></label>
                    <input type="text" name="sku" id="sku" value="{{ old('sku', $product->sku) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Nama <span class="text-red-400">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-300 mb-1">Tipe <span class="text-red-400">*</span></label>
                    <select name="type" id="type" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="goods" {{ old('type', $product->type) == 'goods' ? 'selected' : '' }}>Barang</option>
                        <option value="service" {{ old('type', $product->type) == 'service' ? 'selected' : '' }}>Jasa</option>
                    </select>
                </div>
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-300 mb-1">Kategori</label>
                    <select name="category_id" id="category_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Tanpa Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="unit" class="block text-sm font-medium text-gray-300 mb-1">Satuan</label>
                    <select name="unit" id="unit" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih --</option>
                        @foreach($units as $u)
                            <option value="{{ $u->abbreviation }}" {{ old('unit', $product->unit) == $u->abbreviation ? 'selected' : '' }}>{{ $u->name }} ({{ $u->abbreviation }})</option>
                        @endforeach
                        @foreach(['PCS','UNIT','DUS','KG'] as $defaultUnit)
                            <option value="{{ $defaultUnit }}" {{ old('unit', $product->unit) == $defaultUnit ? 'selected' : '' }}>{{ $defaultUnit }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sell_price" class="block text-sm font-medium text-gray-300 mb-1">Harga Jual (Rp) <span class="text-red-400">*</span></label>
                    <input type="number" step="0.01" name="sell_price" id="sell_price" value="{{ old('sell_price', $product->sell_price) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                </div>
                <div>
                    <label for="buy_price" class="block text-sm font-medium text-gray-300 mb-1">Harga Beli (Rp) <span class="text-red-400">*</span></label>
                    <input type="number" step="0.01" name="buy_price" id="buy_price" value="{{ old('buy_price', $product->buy_price) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="revenue_account_id" class="block text-sm font-medium text-gray-300 mb-1">Akun Pendapatan</label>
                    <select name="revenue_account_id" id="revenue_account_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('revenue_account_id', $product->revenue_account_id) == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="expense_account_id" class="block text-sm font-medium text-gray-300 mb-1">Akun Beban / HPP</label>
                    <select name="expense_account_id" id="expense_account_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('expense_account_id', $product->expense_account_id) == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="rounded bg-gray-800 border-gray-700 text-trusta-teal focus:ring-trusta-teal">
                <label for="is_active" class="text-sm text-gray-300">Aktif (dapat digunakan dalam transaksi)</label>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-800">
                <button type="submit" class="px-6 py-2.5 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-bold rounded-lg text-sm transition-colors shadow-lg">Simpan Perubahan</button>
            </div>
        </div>
    </form>
</x-app-layout>
