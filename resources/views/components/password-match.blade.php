{{-- Keterangan di bawah kolom ulangi password. Formnya perlu x-data="{ pw: '', pw2: '' }" dan input ulangi password x-model="pw2". --}}
<p {{ $attributes->merge(['class' => 'pw-match']) }} x-show="pw2.length" x-cloak :class="pw === pw2 ? 'is-ok' : 'is-bad'" x-text="pw === pw2 ? 'Password sudah sama.' : 'Password belum sama.'"></p>
