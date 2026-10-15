<section>
    <header>
        <h2 class="text-lg font-medium text-white">
            Ubah Password
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            Pastikan akun Anda menggunakan password acak yang panjang dan aman.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-medium text-gray-300 mb-1">Password Saat Ini</label>
            <input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-medium text-gray-300 mb-1">Password Baru</label>
            <input id="update_password_password" name="password" type="password" class="mt-1 block w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-gray-300 mb-1">Konfirmasi Password Baru</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full bg-gray-800 border-gray-700 text-white focus:border-trusta-teal focus:ring-trusta-teal rounded-lg shadow-sm" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-gray-900 font-bold rounded-lg text-sm transition-colors shadow-lg shadow-teal-900/50">Simpan Password</button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-400"
                >Berhasil disimpan.</p>
            @endif
        </div>
    </form>
</section>
