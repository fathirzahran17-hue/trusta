<x-app-layout>
    @section('title', 'Template Akun Default (CoA)')
    @section('subtitle', 'Kelola template akun perkiraan yang otomatis dibuat untuk setiap perusahaan baru yang mendaftar')

    <div x-data="defaultAccountManager()" class="space-y-6">

        {{-- Header Actions --}}
        <div class="flex flex-wrap gap-3 justify-between items-center">
            <button @click="showAddForm = !showAddForm" class="inline-flex items-center px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-gray-900 font-bold rounded-xl text-sm transition-colors shadow-lg shadow-teal-900/50">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Akun Default
            </button>
            <form action="{{ route('superadmin.default-accounts.reset') }}" method="POST" onsubmit="return confirm('Yakin ingin reset semua template ke bawaan standar? Semua perubahan Anda akan hilang.')">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-900/30 text-yellow-400 hover:bg-yellow-900/50 rounded-xl text-sm font-medium transition-colors border border-yellow-800">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Reset ke Bawaan
                </button>
            </form>
        </div>

        {{-- Add/Edit Form --}}
        <div x-show="showAddForm" x-transition style="display: none" class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-2xl">
            <h3 class="text-lg font-bold text-white mb-4" x-text="editingId ? 'Edit Akun Default' : 'Tambah Akun Default Baru'"></h3>
            <form :action="editingId ? `/superadmin/default-accounts/${editingId}` : '{{ route('superadmin.default-accounts.store') }}'" method="POST">
                @csrf
                <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Kode Akun <span class="text-red-400">*</span></label>
                        <input type="text" name="code" x-model="formCode" required placeholder="Contoh: 1-1001" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-300 mb-1">Nama Akun <span class="text-red-400">*</span></label>
                        <input type="text" name="name" x-model="formName" required placeholder="Contoh: Kas" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Tipe Akun <span class="text-red-400">*</span></label>
                        <select name="type" x-model="formType" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="asset">Aset</option>
                            <option value="liability">Kewajiban</option>
                            <option value="equity">Ekuitas</option>
                            <option value="revenue">Pendapatan</option>
                            <option value="cost_of_sales">Harga Pokok Penjualan</option>
                            <option value="expense">Beban Usaha</option>
                            <option value="other_income">Pendapatan Lain-lain</option>
                            <option value="other_expense">Beban Lain-lain</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Saldo Normal <span class="text-red-400">*</span></label>
                        <select name="normal_balance" x-model="formBalance" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="debit">Debit</option>
                            <option value="credit">Kredit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Induk (Parent)</label>
                        <select name="parent_id" x-model="formParentId" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="">-- Tanpa Induk (Root) --</option>
                            @foreach($parentOptions as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-4 mb-4">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input type="checkbox" name="is_system" value="1" x-model="formIsSystem" class="rounded bg-gray-800 border-gray-600 text-trusta-teal focus:ring-trusta-teal">
                        <span>Header / Sistem <span class="text-gray-500">(pengguna tidak bisa menghapus akun ini)</span></span>
                    </label>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-gray-900 font-bold rounded-lg text-sm transition-colors">
                        <span x-text="editingId ? 'Simpan Perubahan' : 'Tambahkan'"></span>
                    </button>
                    <button type="button" @click="resetForm()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">Batal</button>
                </div>
            </form>
        </div>

        {{-- Table --}}
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-800 bg-gray-800/30 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-white">Daftar Template Akun Default</h3>
                <span class="bg-trusta-teal/10 text-trusta-teal text-xs font-bold px-3 py-1.5 rounded-lg border border-trusta-teal/20">
                    Total: {{ $accounts->count() }} Akun
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase tracking-widest border-b border-gray-800">
                            <th class="p-4 font-medium">Kode</th>
                            <th class="p-4 font-medium">Nama Akun</th>
                            <th class="p-4 font-medium">Tipe</th>
                            <th class="p-4 font-medium text-center">Saldo Normal</th>
                            <th class="p-4 font-medium text-center">Tipe Akun</th>
                            <th class="p-4 font-medium">Induk</th>
                            <th class="p-4 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800 text-sm">
                        @forelse($accounts as $account)
                            <tr class="hover:bg-gray-800/50 transition-colors {{ $account->is_system ? 'bg-gray-800/20' : '' }}">
                                <td class="p-4">
                                    <span class="font-mono text-trusta-teal font-bold">{{ $account->code }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="text-white font-medium {{ $account->parent_id ? 'pl-4' : '' }}">
                                        {{ $account->parent_id ? '└ ' : '' }}{{ $account->name }}
                                    </span>
                                </td>
                                <td class="p-4 text-gray-400">{{ $account->type_label }}</td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded text-xs font-bold {{ $account->normal_balance == 'debit' ? 'bg-blue-900/30 text-blue-400 border border-blue-800' : 'bg-purple-900/30 text-purple-400 border border-purple-800' }}">
                                        {{ ucfirst($account->normal_balance) }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    @if($account->is_system)
                                        <span class="px-2 py-1 bg-yellow-900/30 text-yellow-400 border border-yellow-800 rounded text-xs font-bold">Header</span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-800 text-gray-400 border border-gray-700 rounded text-xs">Detail</span>
                                    @endif
                                </td>
                                <td class="p-4 text-gray-500 text-xs">
                                    {{ $account->parent ? $account->parent->code . ' - ' . $account->parent->name : '-' }}
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button type="button"
                                            @click="editAccount({{ $account->id }}, '{{ $account->code }}', '{{ addslashes($account->name) }}', '{{ $account->type }}', '{{ $account->normal_balance }}', '{{ $account->parent_id ?? '' }}', {{ $account->is_system ? 'true' : 'false' }})"
                                            class="inline-flex items-center px-2 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-xs transition-colors border border-gray-600" title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <form action="{{ route('superadmin.default-accounts.destroy', $account->id) }}" method="POST" onsubmit="return confirm('Hapus akun default {{ addslashes($account->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-2 py-1.5 bg-red-900/30 text-red-400 hover:bg-red-900/50 rounded-lg text-xs transition-colors border border-red-800" title="Hapus">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-500">
                                    <p class="mb-2">Belum ada template akun default.</p>
                                    <p class="text-xs">Klik tombol "Reset ke Bawaan" untuk mengisi dengan template standar.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-4 text-sm text-gray-400">
            <p class="font-medium text-gray-300 mb-1">💡 Informasi:</p>
            <p>Template akun ini akan otomatis dibuat untuk setiap perusahaan baru yang mendaftar. Perubahan pada template <strong class="text-white">tidak mempengaruhi</strong> perusahaan yang sudah ada.</p>
        </div>
    </div>

    <script>
        function defaultAccountManager() {
            return {
                showAddForm: false,
                editingId: null,
                formCode: '',
                formName: '',
                formType: 'asset',
                formBalance: 'debit',
                formParentId: '',
                formIsSystem: false,

                editAccount(id, code, name, type, balance, parentId, isSystem) {
                    this.editingId = id;
                    this.formCode = code;
                    this.formName = name;
                    this.formType = type;
                    this.formBalance = balance;
                    this.formParentId = parentId;
                    this.formIsSystem = isSystem;
                    this.showAddForm = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                resetForm() {
                    this.editingId = null;
                    this.formCode = '';
                    this.formName = '';
                    this.formType = 'asset';
                    this.formBalance = 'debit';
                    this.formParentId = '';
                    this.formIsSystem = false;
                    this.showAddForm = false;
                }
            }
        }
    </script>
</x-app-layout>
