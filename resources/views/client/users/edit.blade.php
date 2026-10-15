<x-app-layout>

@section('title', 'Edit Data Staf')
@section('subtitle', 'Perbarui informasi atau status akses karyawan Anda')


<div class="mb-6">
    <a href="{{ route('client.users.index') }}" class="text-gray-400 hover:text-white transition-colors flex items-center text-sm">
        <i class="ph ph-arrow-left mr-2"></i> Kembali ke Daftar Staf
    </a>
</div>

<div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl p-6 max-w-2xl">
    <form action="{{ route('client.users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal">
                @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Alamat Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal">
                @error('email') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ $user->is_active ? 'checked' : '' }} class="w-5 h-5 text-trusta-teal bg-gray-900 border-gray-700 rounded focus:ring-trusta-teal focus:ring-offset-gray-900">
                    <span class="ml-3 text-sm font-medium text-gray-300">Akun Aktif (Dapat Login)</span>
                </label>
            </div>

            <div class="pt-4 mt-4 border-t border-gray-800">
                <h4 class="text-sm font-semibold text-gray-300 mb-4">Ganti Password (Opsional)</h4>
                <p class="text-xs text-gray-500 mb-4">Kosongkan jika tidak ingin mengubah password.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Password Baru</label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" name="password"
                                class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg pr-10 focus:ring-trusta-teal focus:border-trusta-teal">
                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-trusta-teal transition-colors">
                                <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                        @error('password') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Konfirmasi Password Baru</label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" name="password_confirmation"
                                class="w-full bg-gray-900 border border-gray-700 text-white rounded-lg pr-10 focus:ring-trusta-teal focus:border-trusta-teal">
                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-trusta-teal transition-colors">
                                <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-5 border-t border-gray-800 flex justify-end gap-3">
            <a href="{{ route('client.users.index') }}" class="px-5 py-2.5 bg-gray-800 text-gray-300 hover:bg-gray-700 font-medium rounded-lg text-sm transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-bold rounded-lg text-sm transition-colors shadow-lg">Perbarui Staf</button>
        </div>
    </form>
</div>
</x-app-layout>
