<x-app-layout>
    @section('title', 'Ubah Tarif Pajak')
    @section('subtitle', 'Edit data konfigurasi pajak: ' . $tax->name)

    <div class="mb-6">
        <a href="{{ route('client.taxes.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Pajak
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-3xl">
        <form action="{{ route('client.taxes.update', $tax->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="p-8 space-y-8">
                
                <!-- Section 1: Informasi Dasar -->
                <div>
                    <h3 class="form-heading">Informasi Pajak</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block">Nama Pajak <span class="req">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $tax->name) }}" required>
                            @error('name')
                                <p class="field-msg">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="rate" class="block">Tarif Persentase (%) <span class="req">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.01" name="rate" id="rate" value="{{ old('rate', $tax->rate) }}" required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">%</span>
                                </div>
                            </div>
                            @error('rate')
                                <p class="field-msg">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Section 2: Pemetaan Akuntansi (Accounting Mapping) -->
                <div>
                    <h3 class="form-heading">Pemetaan Akun (Buku Besar)</h3>
                    <div class="bg-gray-800/60 rounded-xl p-5 border border-gray-700 mb-6">
                        
                        <div class="space-y-5">
                            <div>
                                <label for="sales_tax_account_id" class="block">
                                    Akun Pajak Penjualan (Keluaran)
                                </label>
                                <select name="sales_tax_account_id" id="sales_tax_account_id">
                                    <option value="">-- Tidak Dipetakan --</option>
                                    @foreach($liabilityAccounts as $acc)
                                        <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('sales_tax_account_id', $tax->sales_tax_account_id) == $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->type === 'current_liability' ? 'Kewajiban Lancar' : 'Kewajiban' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('sales_tax_account_id')
                                    <p class="field-msg">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="purchase_tax_account_id" class="block">
                                    Akun Pajak Pembelian (Masukan)
                                </label>
                                <select name="purchase_tax_account_id" id="purchase_tax_account_id">
                                    <option value="">-- Tidak Dipetakan --</option>
                                    @foreach($assetAccounts as $acc)
                                        <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('purchase_tax_account_id', $tax->purchase_tax_account_id) == $acc->id ? 'selected' : '' }}>
                                            {{ $acc->code }} - {{ $acc->name }} ({{ $acc->type === 'current_asset' ? 'Aset Lancar' : 'Aset' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('purchase_tax_account_id')
                                    <p class="field-msg">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $tax->is_active) ? 'checked' : '' }}
                        class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                    <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                        Status Aktif
                    </label>
                </div>
            </div>

            <div class="doc-actions doc-actions-end">
                <a href="{{ route('client.taxes.index') }}" class="btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
