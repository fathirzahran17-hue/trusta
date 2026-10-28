@php
    $isReq = $kind === 'request';
    $title = $isReq ? 'Pemakaian Barang' : 'Penyesuaian Persediaan';
@endphp
<x-app-layout>
    @section('title', $title)
    @section('subtitle', $isReq ? 'Barang dipakai sendiri (operasional/produksi): stok berkurang dan nilainya dibebankan ke akun biaya' : 'Koreksi stok karena hilang, rusak, atau salah catat — selisihnya otomatis dijurnal')

    <p class="pay-flow">
        @if($isReq)
            Jurnal otomatis: <b>Debit</b> akun biaya yang dipilih / <b>Kredit</b> Persediaan, senilai harga pokok barang.
        @else
            Jurnal otomatis — stok bertambah: <b>Debit</b> Persediaan / <b>Kredit</b> akun lawan; stok berkurang: <b>Debit</b> akun lawan / <b>Kredit</b> Persediaan.
            Untuk menghitung ulang banyak barang sekaligus gunakan <a class="lnk" style="color:var(--accent)" href="{{ route('client.inventory.stock_opname') }}">Stok Opname</a>.
        @endif
    </p>

    <div class="pay-card">
        <div class="pay-card-head"><h3>{{ $isReq ? 'Catat Pemakaian' : 'Buat Penyesuaian' }}</h3></div>
        <form method="POST" action="{{ route($isReq ? 'client.inventory.requests.store' : 'client.inventory.adjustments.store') }}">
            @csrf
            <div class="pay-form-grid">
                <div class="pay-field">
                    <label>Tanggal *</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                </div>
                <div class="pay-field" style="grid-column: span 2;">
                    <label>Barang *</label>
                    <select name="product_id" required>
                        <option value="">-- Pilih Barang --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->sku ? $p->sku . ' - ' : '' }}{{ $p->name }} (stok {{ (float) $p->stock }} {{ $p->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="pay-field">
                    <label>{{ $isReq ? 'Jumlah Dipakai *' : 'Selisih (+ / -) *' }}</label>
                    <input type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" required @if($isReq) min="0.01" @endif placeholder="{{ $isReq ? 'mis. 3' : 'mis. -2 (hilang) atau 5 (lebih)' }}">
                </div>
                <div class="pay-field" style="grid-column: span 2;">
                    <label>{{ $isReq ? 'Dibebankan ke Akun *' : 'Akun Lawan *' }}</label>
                    <select name="account_id" required>
                        @foreach($accounts as $a)
                            <option value="{{ $a->id }}" @selected(old('account_id', $defaultAccount) == $a->id)>{{ $a->code }} - {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pay-field" style="grid-column: span 2;">
                    <label>Keterangan</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" placeholder="{{ $isReq ? 'mis. dipakai bagian produksi' : 'mis. barang rusak' }}">
                </div>
            </div>
            <div class="pay-foot">
                <span class="pay-total">Nilai dihitung dari harga pokok barang saat ini.</span>
                <button type="submit" class="btn-primary">{{ $isReq ? 'Catat Pemakaian' : 'Simpan Penyesuaian' }}</button>
            </div>
        </form>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat</h3><span class="sub">{{ $movements->total() }} catatan</span></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Tanggal</th><th>Barang</th><th class="r">Jumlah</th><th class="r">Nilai</th><th>Keterangan</th><th>Jurnal</th><th></th></tr></thead>
                <tbody>
                @forelse($movements as $m)
                    <tr class="{{ $m->voided_at ? 'pay-void-row' : '' }}">
                        <td>{{ \Carbon\Carbon::parse($m->date)->format('d/m/Y') }}</td>
                        <td>{{ $m->product->name ?? '-' }}</td>
                        <td class="r" style="color: {{ $m->type === 'in' ? '#15803d' : 'var(--danger)' }}; font-weight:700;">{{ $m->type === 'in' ? '+' : '-' }}{{ (float) $m->quantity }}</td>
                        <td class="r">{{ number_format((float) $m->quantity * (float) $m->unit_cost, 2, ',', '.') }}</td>
                        <td class="wrap">{{ $m->notes }}</td>
                        <td>@if($m->journal_id && isset($journals[$m->journal_id]))<a class="lnk" href="{{ route('client.journals.show', $m->journal_id) }}">{{ $journals[$m->journal_id] }}</a>@else - @endif</td>
                        <td class="r">
                            @if($m->voided_at)
                                <span class="st st-void" style="text-decoration:none">Dibatalkan</span>
                            @else
                                <form method="POST" action="{{ route('client.inventory.movements.cancel', $m->id) }}" style="display:inline" onsubmit="return confirm('Batalkan catatan ini? Stok dikembalikan dan dibuat jurnal balik bertanggal hari ini. Catatannya tetap ada (ditandai Dibatalkan).')">@csrf
                                    <button class="pay-mini dng" type="submit">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="pay-empty">Belum ada catatan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<div style="padding: 10px 18px;">{{ $movements->links() }}</div>@endif
    </div>
</x-app-layout>
