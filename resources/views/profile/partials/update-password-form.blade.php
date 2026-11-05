<section>
    <header>
        <h2 class="text-lg font-medium text-white">
            Ubah Password
        </h2>

        <p class="field-hint">
            Pastikan akun Anda menggunakan password acak yang panjang dan aman.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" x-data="{ pw: '', pw2: '' }">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block">Password Saat Ini</label>
            <div class="auth-input-wrap" x-data="{ show: false }">
                <input id="update_password_current_password" name="current_password" type="password" :type="show ? 'text' : 'password'" style="padding-right: 44px;" autocomplete="current-password" />
                <x-password-toggle />
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password" class="block">Password Baru</label>
            <div class="auth-input-wrap" x-data="{ show: false }">
                <input id="update_password_password" name="password" type="password" :type="show ? 'text' : 'password'" x-model="pw" style="padding-right: 44px;" autocomplete="new-password" />
                <x-password-toggle />
            </div>
            <x-password-rules />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block">Konfirmasi Password Baru</label>
            <div class="auth-input-wrap" x-data="{ show: false }">
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" :type="show ? 'text' : 'password'" x-model="pw2" style="padding-right: 44px;" autocomplete="new-password" />
                <x-password-toggle />
            </div>
            <x-password-match />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button class="btn-primary">Simpan Password</button>

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
