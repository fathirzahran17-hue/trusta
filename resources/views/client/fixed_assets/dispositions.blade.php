<x-app-layout>
    @section('title', 'Pelepasan Aset Tetap')
    @section('subtitle', 'Catat aset yang dijual atau dihapus (rusak/hilang) beserta laba/rugi pelepasannya')

    @php $rp = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

    <p class="pay-flow">
        Jurnal otomatis: <b>Debit</b> Akumulasi Penyusutan + <b>Debit</b> Kas/Bank (harga jual) / <b>Kredit</b> Akun Aset (harga perolehan).
        Selisihnya menjadi <b>Laba</b> (kredit pendapatan lain) atau <b>Rugi</b> (debit beban lain). Jalankan dulu penyusutan sampai bulan pelepasan agar nilai bukunya tepat.
    </p>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Aset Aktif ({{ $active->count() }})</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Kode</th><th>Nama Aset</th><th>Tgl Perolehan</th><th class="r">Harga Perolehan</th><th class="r">Akum. Penyusutan</th><th class="r">Nilai Buku</th><th></th></tr></thead>
                @forelse($active as $a)
                    @php $book = (float) $a->acquisition_cost - (float) $a->accumulated_depreciation; @endphp
                    <tbody x-data="{ open: {{ old('asset_id') == $a->id ? 'true' : 'false' }}, price: '{{ old('asset_id') == $a->id ? old('proceeds') : '' }}' }" style="border:0">
                    <tr>
                        <td>{{ $a->code }}</td>
                        <td class="wrap">{{ $a->name }}</td>
                        <td>{{ optional($a->acquisition_date)->format('d/m/Y') }}</td>
                        <td class="r">{{ $rp($a->acquisition_cost) }}</td>
                        <td class="r">{{ $rp($a->accumulated_depreciation) }}</td>
                        <td class="r"><b>{{ $rp($book) }}</b></td>
                        <td class="r"><button type="button" class="pay-mini" @click="open = !open" x-text="open ? 'Tutup' : 'Lepas / Jual'"></button></td>
                    </tr>
                    <tr x-show="open" x-cloak>
                        <td colspan="7" style="background: var(--surface-2); white-space: normal;">
                            <form method="POST" action="{{ route('client.fixed_assets.dispose', $a->id) }}" onsubmit="return confirm('Lepas aset {{ $a->code }}? Aset tidak lagi disusutkan dan jurnal pelepasan dibuat.')">
                                @csrf
                                <input type="hidden" name="asset_id" value="{{ $a->id }}">
                                <div class="pay-form-grid" style="padding: 8px 0;">
                                    <div class="pay-field"><label>Tanggal *</label><input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required></div>
                                    <div class="pay-field"><label>Harga Jual (0 bila dihapus/dibuang)</label><input type="text" name="proceeds" class="money-in" x-model="price" placeholder="0,00"></div>
                                    <div class="pay-field"><label>Diterima ke (bila dijual)</label>
                                        <select name="proceeds_account_id"><option value="">-- pilih --</option>@foreach($proceedsAccounts as $acc)<option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>@endforeach</select></div>
                                    <div class="pay-field"><label>Akun Aset *</label>
                                        <select name="asset_account_id" required>@foreach($assetAccounts as $acc)<option value="{{ $acc->id }}" @selected($a->asset_account_id == $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach</select></div>
                                    @if((float) $a->accumulated_depreciation > 0)
                                    <div class="pay-field"><label>Akun Akumulasi Penyusutan *</label>
                                        <select name="accumulated_account_id">@foreach($accumAccounts as $acc)<option value="{{ $acc->id }}" @selected($a->accumulated_account_id == $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach</select></div>
                                    @endif
                                    <div class="pay-field"><label>Akun Laba Pelepasan</label>
                                        <select name="gain_account_id">@foreach($gainAccounts as $acc)<option value="{{ $acc->id }}" @selected($defaults['gain'] == $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach</select></div>
                                    <div class="pay-field"><label>Akun Rugi Pelepasan</label>
                                        <select name="loss_account_id">@foreach($lossAccounts as $acc)<option value="{{ $acc->id }}" @selected($defaults['loss'] == $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>@endforeach</select></div>
                                    <div class="pay-field"><label>Catatan</label><input type="text" name="notes" maxlength="200" placeholder="mis. dijual ke PT X"></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding-bottom:6px;">
                                    <span class="pay-total" x-text="(() => { const v = (window.parseMoney ? window.parseMoney(price) : parseFloat(price) || 0) - {{ round($book, 2) }}; return v > 0 ? 'Perkiraan LABA pelepasan: Rp ' + window.fmtMoney(v) : (v < 0 ? 'Perkiraan RUGI pelepasan: Rp ' + window.fmtMoney(-v) : 'Tidak ada laba/rugi (harga jual = nilai buku)'); })()"></span>
                                    <button type="submit" class="btn-primary">Simpan Pelepasan</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="7" class="pay-empty">Tidak ada aset aktif.</td></tr></tbody>
                @endforelse
            </table>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Sudah Dilepas ({{ $disposed->count() }})</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Kode</th><th>Nama Aset</th><th>Status</th><th>Tanggal</th><th class="r">Harga Jual</th><th>Jurnal</th><th></th></tr></thead>
                <tbody>
                @forelse($disposed as $a)
                    <tr>
                        <td>{{ $a->code }}</td>
                        <td class="wrap">{{ $a->name }}</td>
                        <td>{{ ['dijual' => 'Dijual', 'dihapus' => 'Dihapus', 'arsip' => 'Arsip'][$a->status] ?? ucfirst($a->status) }}</td>
                        <td>{{ $a->disposal_date ? \Carbon\Carbon::parse($a->disposal_date)->format('d/m/Y') : '-' }}</td>
                        <td class="r">{{ $a->disposal_proceeds !== null ? $rp($a->disposal_proceeds) : '-' }}</td>
                        <td>@if($a->disposal_journal_id && isset($journals[$a->disposal_journal_id]))<a class="lnk" href="{{ route('client.journals.show', $a->disposal_journal_id) }}">{{ $journals[$a->disposal_journal_id] }}</a>@else - @endif</td>
                        <td class="r">
                            @if($a->disposal_journal_id)
                                <form method="POST" action="{{ route('client.fixed_assets.cancel_disposal', $a->id) }}" style="display:inline" onsubmit="return confirm('Batalkan pelepasan {{ $a->code }}? Aset aktif kembali dan dibuat jurnal balik bertanggal hari ini.')">@csrf
                                    <button type="submit" class="pay-mini dng">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="pay-empty">Belum ada aset yang dilepas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
