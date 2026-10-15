<x-app-layout>
    @section('title', 'Kategori Aset Tetap')
    @section('subtitle', 'Pengelompokkan aset tetap berdasarkan jenis untuk memudahkan manajemen dan pelaporan')

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.fixed_assets.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Aset Tetap
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Form Tambah Kategori --}}
        <div class="lg:col-span-1">
            <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-trusta-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Kategori Baru
                </h3>
                
                <form action="{{ route('client.fixed_assets.categories.store') }}" method="POST">
                    @csrf
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Nama Kategori <span class="text-red-400">*</span></label>
                            <input type="text" name="name" id="name" required placeholder="Misal: Kendaraan, Gedung"
                                class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm text-sm">
                        </div>
                        
                        <div>
                            <label for="depreciation_method" class="block text-sm font-medium text-gray-300 mb-1">Metode Penyusutan Default <span class="text-red-400">*</span></label>
                            <select name="depreciation_method" id="depreciation_method" required
                                class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm text-sm">
                                <option value="straight_line">Garis Lurus (Straight Line)</option>
                                <option value="declining_balance">Saldo Menurun (Declining Balance)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="useful_life_years" class="block text-sm font-medium text-gray-300 mb-1">Umur Manfaat Default (Tahun) <span class="text-red-400">*</span></label>
                            <input type="number" name="useful_life_years" id="useful_life_years" required min="1" max="50" value="4"
                                class="w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm text-sm">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full mt-6 px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-semibold rounded-lg text-sm transition-colors shadow-lg shadow-teal-900/50">
                        Simpan Kategori
                    </button>
                </form>
            </div>
        </div>
        
        {{-- Daftar Kategori --}}
        <div class="lg:col-span-2">
            <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4">Nama Kategori</th>
                                <th class="px-6 py-4">Metode Penyusutan</th>
                                <th class="px-6 py-4 text-center">Umur (Thn)</th>
                                <th class="px-6 py-4 text-center">Jml Aset</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @forelse($categories as $cat)
                            <tr class="hover:bg-[#0F172A]/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-white">{{ $cat->name }}</td>
                                <td class="px-6 py-4 text-gray-400">
                                    {{ $cat->depreciation_method == 'straight_line' ? 'Garis Lurus' : 'Saldo Menurun' }}
                                </td>
                                <td class="px-6 py-4 text-center text-gray-300">{{ $cat->useful_life_years }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">
                                        {{ $cat->fixed_assets_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <form action="{{ route('client.fixed_assets.categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Yakin hapus kategori ini? Pastikan tidak ada aset yang terhubung.')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-300 transition-colors" title="Hapus">
                                            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Belum ada data kategori aset. Silakan tambahkan di form sebelah kiri.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
