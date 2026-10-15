<x-app-layout>
    @section('title', 'Gudang (Warehouse)')
    @section('subtitle', 'Manajemen master data gudang atau lokasi penyimpanan')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Form Tambah --}}
        <div class="lg:col-span-1">
            <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Tambah Gudang Baru</h3>
                <form action="{{ route('client.inventory.warehouses.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Kode <span class="text-red-400">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="GDG-01" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Nama Gudang <span class="text-red-400">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Gudang Utama" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Alamat</label>
                        <input type="text" name="address" value="{{ old('address') }}" placeholder="Alamat lokasi..." class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Telepon</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="08xxx" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Penanggung Jawab</label>
                        <input type="text" name="person_in_charge" value="{{ old('person_in_charge') }}" placeholder="Nama PIC" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-bold rounded-lg text-sm transition-colors">Tambah Gudang</button>
                </form>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="lg:col-span-2">
            <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]">
                    <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Daftar Gudang ({{ $warehouses->count() }})</h3>
                </div>
                <table class="w-full text-sm text-left">
                    <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">Alamat</th>
                            <th class="px-6 py-3">PIC</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($warehouses as $wh)
                        <tr class="hover:bg-[#0F172A]/50 transition-colors">
                            <td class="px-6 py-3 font-mono text-trusta-teal font-bold">{{ $wh->code }}</td>
                            <td class="px-6 py-3 text-white font-medium">{{ $wh->name }}</td>
                            <td class="px-6 py-3 text-gray-400">{{ $wh->address ?? '-' }}</td>
                            <td class="px-6 py-3 text-gray-400">{{ $wh->person_in_charge ?? '-' }}</td>
                            <td class="px-6 py-3 text-center">
                                <form action="{{ route('client.inventory.warehouses.destroy', $wh) }}" method="POST" onsubmit="return confirm('Yakin hapus gudang ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada data gudang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
