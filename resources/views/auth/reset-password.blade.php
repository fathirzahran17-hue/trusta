<x-guest-layout>
    <h1 class="auth-title">Buat password baru</h1>
    <p class="auth-sub">Masukkan password baru untuk akun Anda.</p>

    <form method="POST" action="{{ route('password.store') }}" x-data="{ pw: '', pw2: '' }">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label for="email" class="auth-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <div class="auth-field" x-data="{ show: false }">
            <label for="password" class="auth-label">Password baru</label>
            <div class="auth-input-wrap">
                <input id="password" type="password" :type="show ? 'text' : 'password'" name="password" x-model="pw" required autofocus autocomplete="new-password" style="padding-right: 44px;">
                <x-password-toggle />
            </div>
            <x-password-rules />
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <div class="auth-field" x-data="{ show: false }">
            <label for="password_confirmation" class="auth-label">Ulangi password</label>
            <div class="auth-input-wrap">
                <input id="password_confirmation" type="password" :type="show ? 'text' : 'password'" name="password_confirmation" x-model="pw2" required autocomplete="new-password" style="padding-right: 44px;">
                <x-password-toggle />
            </div>
            <x-password-match />
            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-error" />
        </div>

        <button type="submit" class="auth-btn">Simpan password</button>
    </form>
</x-guest-layout>
