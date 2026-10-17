<x-guest-layout>
    <h1 class="auth-title">Reset password</h1>
    <p class="auth-sub">Masukkan email akun Anda. Kami akan mengirimkan tautan untuk membuat password baru.</p>

    <x-auth-session-status class="auth-status" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="nama@perusahaan.com">
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <button type="submit" class="auth-btn">Kirim tautan reset</button>
    </form>

    <p class="auth-foot"><a href="{{ route('login') }}" class="auth-link">&larr; Kembali ke halaman masuk</a></p>
</x-guest-layout>
