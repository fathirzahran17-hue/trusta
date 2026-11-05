{{-- Tombol ganti bahasa. Pemakaian: @include('partials.language-toggle', ['floating' => true]) --}}
@php $nextLocale = app()->getLocale() === 'en' ? 'id' : 'en'; @endphp
<form method="POST" action="{{ route('locale.update') }}" data-no-guard class="language-toggle {{ !empty($floating) ? 'language-toggle-floating' : '' }}">
    @csrf
    <input type="hidden" name="locale" value="{{ $nextLocale }}">
    <button type="submit" class="header-icon-btn language-btn" data-tooltip="{{ $nextLocale === 'en' ? 'Switch to English' : 'Ganti ke Bahasa Indonesia' }}" aria-label="{{ $nextLocale === 'en' ? 'Switch to English' : 'Ganti ke Bahasa Indonesia' }}">{{ strtoupper($nextLocale) }}</button>
</form>
