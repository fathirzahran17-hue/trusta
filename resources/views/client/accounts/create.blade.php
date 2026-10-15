<x-app-layout>
    @section('title', 'Tambah Akun Baru')
    @section('subtitle', 'Tambahkan akun buku besar baru ke dalam Chart of Accounts')

    <div class="mb-6">
        <a href="{{ route('client.accounts.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Akun
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-3xl">
        <form action="{{ route('client.accounts.store') }}" method="POST">
            @csrf
            
            <div class="p-8 space-y-6">
                <!-- Akun Induk -->
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-gray-300 mb-2">Akun Induk (Sub-Akun dari)</label>
                    <select name="parent_id" id="parent_id"
                        class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                        <option value="">-- Tidak ada (Ini adalah Akun Master) --</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                {{ $parent->code }} - {{ $parent->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Pilih jika akun ini adalah turunan dari akun lain.</p>
                    @error('parent_id')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Kode & Nama Akun -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-300 mb-2">Kode Akun <span class="text-red-400">*</span></label>
                        <input type="text" name="code" id="code" value="{{ old('code') }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: 1-10005">
                        @error('code')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Nama Akun <span class="text-red-400">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                            placeholder="Contoh: Kas Kecil">
                        @error('name')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Tipe & Saldo Normal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-300 mb-2">Kategori (Tipe Akun) <span class="text-red-400">*</span></label>
                        <select name="type" id="type" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="">-- Pilih Kategori --</option>
                            <option value="asset" {{ old('type') == 'asset' ? 'selected' : '' }}>Asset (Harta)</option>
                            <option value="liability" {{ old('type') == 'liability' ? 'selected' : '' }}>Liability (Kewajiban)</option>
                            <option value="equity" {{ old('type') == 'equity' ? 'selected' : '' }}>Equity (Modal)</option>
                            <option value="revenue" {{ old('type') == 'revenue' ? 'selected' : '' }}>Income (Pendapatan)</option>
                            <option value="cost_of_sales" {{ old('type') == 'cost_of_sales' ? 'selected' : '' }}>Cost of Sales (HPP)</option>
                            <option value="expense" {{ old('type') == 'expense' ? 'selected' : '' }}>Expense (Beban)</option>
                            <option value="other_income" {{ old('type') == 'other_income' ? 'selected' : '' }}>Other Income (Pendapatan Lain-lain)</option>
                            <option value="other_expense" {{ old('type') == 'other_expense' ? 'selected' : '' }}>Other Expense (Beban Lain-lain)</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="normal_balance" class="block text-sm font-medium text-gray-300 mb-2">Saldo Normal <span class="text-red-400">*</span></label>
                        <select name="normal_balance" id="normal_balance" required
                            class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm">
                            <option value="debit" {{ old('normal_balance') == 'debit' ? 'selected' : '' }}>Debit</option>
                            <option value="credit" {{ old('normal_balance') == 'credit' ? 'selected' : '' }}>Kredit</option>
                        </select>
                        @error('normal_balance')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Saldo Awal & Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="opening_balance" class="block text-sm font-medium text-gray-300 mb-2">Saldo Awal</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">Rp</span>
                            </div>
                            <input type="hidden" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', 0) }}">
                            <input type="text" id="opening_balance_display" value="{{ old('opening_balance', 0) }}"
                                onkeyup="formatRupiah(this, 'opening_balance')"
                                class="w-full pl-10 bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm"
                                placeholder="0">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Isi jika ini adalah akun neraca dengan saldo bawaan.</p>
                        @error('opening_balance')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col space-y-4 pt-8">
                        <div class="flex items-center">
                            <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                            <label for="is_active" class="ml-2 block text-sm font-medium text-gray-300">
                                Akun Aktif
                                <p class="text-xs text-gray-500 font-normal">Akun tidak aktif tidak akan muncul di opsi jurnal.</p>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input id="is_cash_bank" name="is_cash_bank" type="checkbox" value="1" {{ old('is_cash_bank', false) ? 'checked' : '' }}
                                class="w-5 h-5 text-trusta-teal bg-gray-800 border-gray-700 rounded focus:ring-trusta-teal focus:ring-2">
                            <label for="is_cash_bank" class="ml-2 block text-sm font-medium text-gray-300">
                                Tampilkan di Menu Kas & Bank
                                <p class="text-xs text-gray-500 font-normal">Centang jika ini adalah sub-akun kas/bank yang dapat bertransaksi.</p>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-8 py-4 bg-gray-800/50 border-t border-gray-700/50 flex justify-end space-x-3">
                <a href="{{ route('client.accounts.index') }}" class="px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-sm font-medium">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50">
                    Simpan Akun
                </button>
            </div>
        </form>
    </div>

    <!-- Script for smart defaulting -->
    <script>
        document.getElementById('type').addEventListener('change', function() {
            const type = this.value;
            const balanceSelect = document.getElementById('normal_balance');
            
            if (type === 'asset' || type === 'expense') {
                balanceSelect.value = 'debit';
            } else if (type === 'liability' || type === 'equity' || type === 'revenue') {
                balanceSelect.value = 'credit';
            }
        });
    </script>
</x-app-layout>

<script>
function formatRupiah(element, hiddenElementId) {
    let value = element.value.toString().replace(/[^,\d]/g, '');
    let split = value.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    element.value = rupiah;
    
    // Update hidden value
    let realValue = value ? value.replace(/,/g, '.') : 0;
    document.getElementById(hiddenElementId).value = realValue;
}

document.addEventListener('DOMContentLoaded', function() {
    let openingDisplay = document.getElementById('opening_balance_display');
    if(openingDisplay && openingDisplay.value) formatRupiah(openingDisplay, 'opening_balance');
});
</script>
