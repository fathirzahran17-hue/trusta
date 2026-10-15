<x-app-layout>
    @section('title', 'Tambah Tarif Pajak')
    @section('subtitle', 'Buat konfigurasi pajak baru dan petakan ke akun buku besar')

    <div class="mb-6">
        <a href="{{ route('client.taxes.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pajak
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-3xl">
        <form action="{{ route('client.taxes.store') }}" method="POST">
            @csrf
            
            <div class="p-8 space-y-8">
                
                <!-- Section 1: Informasi Dasar -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4 border-b border-gray-700 pb-2">Informasi Pajak</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nama Pajak <span class="text-red-400">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                                placeholder="Contoh: PPN 11% atau PPh 23 (2%)">
                            @error('name')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="rate" class="block text-sm font-medium text-gray-300 mb-2">Tarif Persentase (%) <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.01" name="rate" id="rate" value="{{ old('rate') }}" required
                                    class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                                    placeholder="11">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">%</span>
                                </div>
                            </div>
                            @error('rate')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 2: Pemetaan Akuntansi (Accounting Mapping) -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4 border-b border-gray-700 pb-2">Pemetaan Akun (Buku Besar)</h3>
                    <div class="bg-gray-800/60 rounded-xl p-5 border border-gray-700 mb-6">
                        <p class="text-sm text-gray-300 mb-4">Pilih ke mana pajak ini akan dicatat ketika terjadi transaksi Penjualan atau Pembelian.</p>
                        
                        <div class="space-y-5">
                            <div>
                                <label for="sales_tax_account_id" class="block text-sm font-medium text-gray-300 mb-2">
                                    Akun Pajak Penjualan (Keluaran)
                                </label>
                                <select name="sales_tax_account_id" id="sales_tax_account_id"
                                    class="w-full bg-gray-900 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                                    <option value="">-- Tidak Dipetakan (Kosongkan jika tidak ada) --</option>
                                    @foreach($liabilityAccounts as $acc)
                                        <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('sales_tax_account_id') == $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->type === 'current_liability' ? 'Kewajiban Lancar' : 'Kewajiban' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Pajak penjualan biasanya menjadi Hutang Pajak (Liability).</p>
                                @error('sales_tax_account_id')
                                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="purchase_tax_account_id" class="block text-sm font-medium text-gray-300 mb-2">
                                    Akun Pajak Pembelian (Masukan)
                                </label>
                                <select name="purchase_tax_account_id" id="purchase_tax_account_id"
                                    class="w-full bg-gray-900 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                                    <option value="">-- Tidak Dipetakan (Kosongkan jika tidak ada) --</option>
                                    @foreach($assetAccounts as $acc)
                                        <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('purchase_tax_account_id') == $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->type === 'current_asset' ? 'Aset Lancar' : 'Aset' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Pajak pembelian biasanya menjadi Pajak Dibayar di Muka (Asset).</p>
                                @error('purchase_tax_account_id')
                                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                    <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                        Status Aktif
                    </label>
                </div>
            </div>

            <div class="px-8 py-4 bg-gray-800/50 border-t border-gray-700/50 flex justify-end space-x-3">
                <a href="{{ route('client.taxes.index') }}" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
                    Simpan Tarif Pajak
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
