{{--
    Daftar syarat password yang berubah saat mengetik. Formnya perlu x-data="{ pw: '', pw2: '' }" dan input password x-model="pw".
    Aturannya mengikuti Password::defaults() di AppServiceProvider; bila aturan di sana berubah, ubah juga di sini.
--}}
@php
    $rules = [
        ['Minimal 8 karakter', '[...pw].length >= 8'],
        ['Ada huruf besar (A-Z)', '/\p{Lu}/u.test(pw)'],
        ['Ada huruf kecil (a-z)', '/\p{Ll}/u.test(pw)'],
        ['Ada angka (0-9)', '/\p{N}/u.test(pw)'],
        ['Ada simbol, misalnya ! # $ % _ -', '/[\p{Z}\p{S}\p{P}]/u.test(pw)'],
    ];
@endphp
<div {{ $attributes->merge(['class' => 'pw-rules']) }}>
    <p class="pw-rules-title">Syarat password:</p>
    <ul>
        @foreach ($rules as [$label, $check])
            <li x-data="{ get ok() { return {{ $check }} } }" :class="ok ? 'is-ok' : (pw.length ? 'is-bad' : '')">
                <svg x-show="ok" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="!ok && pw.length" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M18 6L6 18"/></svg>
                <svg x-show="!ok && !pw.length" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="7" stroke-width="2.5"/></svg>
                <span>{{ $label }}<span class="sr-only" x-text="ok ? ' (terpenuhi)' : ' (belum terpenuhi)'"></span></span>
            </li>
        @endforeach
    </ul>
    <p class="pw-rules-note">Password yang pernah bocor di internet, seperti Password123!, juga akan ditolak saat disimpan. Pakai gabungan kata yang tidak umum.</p>
</div>
