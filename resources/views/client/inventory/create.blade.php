<x-app-layout>
    @section('title', 'Tambah Barang / Jasa')
    @section('subtitle', 'Catat data produk atau jasa baru')

    <div class="mb-6">
        <a href="{{ route('client.inventory.index') }}" class="text-trusta-teal hover:text-white text-sm flex items-center transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar
        </a>
    </div>

    <form action="{{ route('client.inventory.store') }}" method="POST">
        @csrf
        <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-300 mb-1">Kode / SKU <span class="text-red-400">*</span></label>
                    <input type="text" name="sku" id="sku" value="{{ old('sku') }}" required placeholder="Contoh: BRG-001" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Nama Barang / Jasa <span class="text-red-400">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Contoh: Laptop ASUS VivoBook" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-300 mb-1">Tipe <span class="text-red-400">*</span></label>
                    <select name="type" id="type" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="goods" {{ old('type') == 'goods' ? 'selected' : '' }}>Barang (Goods)</option>
                        <option value="service" {{ old('type') == 'service' ? 'selected' : '' }}>Jasa (Service)</option>
                    </select>
                </div>
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-300 mb-1">Kategori</label>
                    <select name="category_id" id="category_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Tanpa Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="unit" class="block text-sm font-medium text-gray-300 mb-1">Satuan</label>
                    <select name="unit" id="unit" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih Satuan --</option>
                        @foreach($units as $u)
                            <option value="{{ $u->abbreviation }}" {{ old('unit') == $u->abbreviation ? 'selected' : '' }}>{{ $u->name }} ({{ $u->abbreviation }})</option>
                        @endforeach
                        <option value="PCS" {{ old('unit') == 'PCS' ? 'selected' : '' }}>Pcs</option>
                        <option value="UNIT" {{ old('unit') == 'UNIT' ? 'selected' : '' }}>Unit</option>
                        <option value="DUS" {{ old('unit') == 'DUS' ? 'selected' : '' }}>Dus</option>
                        <option value="KG" {{ old('unit') == 'KG' ? 'selected' : '' }}>Kg</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sell_price" class="block text-sm font-medium text-gray-300 mb-1">Harga Jual (Rp) <span class="text-red-400">*</span></label>
                    <input type="number" step="0.01" name="sell_price" id="sell_price" value="{{ old('sell_price', 0) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('sell_price')" class="mt-1" />
                </div>
                <div>
                    <label for="buy_price" class="block text-sm font-medium text-gray-300 mb-1">Harga Beli (Rp) <span class="text-red-400">*</span></label>
                    <input type="number" step="0.01" name="buy_price" id="buy_price" value="{{ old('buy_price', 0) }}" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    <x-input-error :messages="$errors->get('buy_price')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="revenue_account_id" class="block text-sm font-medium text-gray-300 mb-1">Akun Pendapatan</label>
                    <select name="revenue_account_id" id="revenue_account_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih Akun --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('revenue_account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="expense_account_id" class="block text-sm font-medium text-gray-300 mb-1">Akun Beban / HPP</label>
                    <select name="expense_account_id" id="expense_account_id" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <option value="">-- Pilih Akun --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('expense_account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-800">
                <button type="submit" class="px-6 py-2.5 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-bold rounded-lg text-sm transition-colors shadow-lg">Simpan Barang / Jasa</button>
            </div>
        </div>
    </form>
</x-app-layout>
