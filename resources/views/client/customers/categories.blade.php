<x-app-layout>
    @section('title', 'Kategori Pelanggan')
    @section('subtitle', 'Pengelompokkan pelanggan berdasarkan segmen, wilayah, atau tipe')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="doc-card doc-pad">
                <h3 class="doc-section-title mb-4">Tambah Kategori</h3>
                <form action="{{ route('client.customers.categories.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block">Nama Kategori <span class="req">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Misal: Grosir, Retail, Corporate">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <label class="block">Deskripsi</label>
                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Keterangan...">
                    </div>
                    <button type="submit" class="btn-primary w-full">Tambah Kategori</button>
                </form>
            </div>
        </div>
        <div class="lg:col-span-2">
            <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]">
                    <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Daftar Kategori ({{ $categories->count() }})</h3>
                </div>
                <table class="w-full text-sm text-left">
                    <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs">
                        <tr><th class="px-6 py-3">No</th><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Deskripsi</th><th class="px-6 py-3 text-center">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($categories as $idx => $cat)
                        <tr class="hover:bg-[#0F172A]/50 transition-colors">
                            <td class="px-6 py-3 text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-6 py-3 text-white font-medium">{{ $cat->name }}</td>
                            <td class="px-6 py-3 text-gray-400">{{ $cat->description ?? '-' }}</td>
                            <td class="px-6 py-3 text-center">
                                <form action="{{ route('client.customers.categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Yakin hapus?')">@csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Belum ada kategori pelanggan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
