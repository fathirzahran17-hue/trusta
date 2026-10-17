<x-guest-layout>
    <h1 class="auth-title">Buat akun baru</h1>
    <p class="auth-sub">Daftarkan perusahaan Anda dan mulai mencatat dalam hitungan menit.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="auth-field">
            <label for="company_name" class="auth-label">Nama perusahaan / usaha <span class="req">*</span></label>
            <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" required autofocus autocomplete="organization" placeholder="PT Maju Bersama">
            <x-input-error :messages="$errors->get('company_name')" class="auth-error" />
        </div>

        <div class="auth-field">
            <label for="name" class="auth-label">Nama lengkap Anda <span class="req">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
            <x-input-error :messages="$errors->get('name')" class="auth-error" />
        </div>

        <div class="auth-field">
            <label for="email" class="auth-label">Email <span class="req">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="nama@perusahaan.com">
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <div class="auth-field" x-data="{ show: false }">
            <label for="password" class="auth-label">Password <span class="req">*</span></label>
            <div class="auth-input-wrap">
                <input id="password" type="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" style="padding-right: 44px;">
                <button type="button" class="auth-eye" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                    <svg x-show="!show" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg x-show="show" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.562c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
            </div>
            <p class="auth-hint">Minimal 8 karakter, kombinasi huruf, angka, dan simbol.</p>
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <div class="auth-field" x-data="{ show: false }">
            <label for="password_confirmation" class="auth-label">Ulangi password <span class="req">*</span></label>
            <div class="auth-input-wrap">
                <input id="password_confirmation" type="password" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" style="padding-right: 44px;">
                <button type="button" class="auth-eye" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                    <svg x-show="!show" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg x-show="show" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.562c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-error" />
        </div>

        <button type="submit" class="auth-btn">Daftar</button>
    </form>

    <div class="auth-sep">atau</div>

    <a href="{{ route('google.login') }}" class="auth-btn auth-btn-ghost">
        <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Daftar dengan Google
    </a>

    <p class="auth-foot">Sudah punya akun? <a href="{{ route('login') }}" class="auth-link">Masuk</a></p>
</x-guest-layout>
