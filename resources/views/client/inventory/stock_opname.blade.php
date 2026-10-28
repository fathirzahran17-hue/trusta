<x-app-layout>
    @section('title', 'Stok Opname')
    @section('subtitle', 'Hitung fisik barang di gudang; selisih dengan stok sistem otomatis disesuaikan dan dijurnal')

    <p class="pay-flow">
        Langkah: <b>1.</b> Cetak/lihat daftar di bawah &nbsp;→&nbsp; <b>2.</b> Hitung fisik di gudang &nbsp;→&nbsp; <b>3.</b> Isi kolom <b>Stok Fisik</b> (kosongkan yang tidak dihitung) &nbsp;→&nbsp; <b>4.</b> Simpan.
        Selisih kurang: <b>Debit</b> akun lawan / <b>Kredit</b> Persediaan; selisih lebih: sebaliknya.
    </p>

    <form method="POST" action="{{ route('client.inventory.stock_opname.store') }}" x-data="{ q: '' }">
        @csrf
        <div class="pay-card">
            <div class="pay-form-grid">
                <div class="pay-field">
                    <label>Tanggal Opname *</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                </div>
                <div class="pay-field" style="grid-column: span 2;">
                    <label>Akun Lawan Selisih *</label>
                    <select name="account_id" required>
                        @foreach($accounts as $a)
                            <option value="{{ $a->id }}" @selected(old('account_id', $defaultAccount) == $a->id)>{{ $a->code }} - {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pay-field" style="grid-column: span 2;">
                    <label>Catatan</label>
                    <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}" placeholder="mis. opname akhir bulan, gudang utama">
                </div>
            </div>
        </div>

        <div class="pay-card">
            <div class="pay-card-head">
                <h3>Daftar Barang ({{ $products->count() }})</h3>
                <input type="search" x-model="q" placeholder="Cari barang…" style="max-width: 240px; padding: 5px 10px; font-size: .78rem;">
            </div>
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>SKU</th><th>Barang</th><th>Kategori</th><th class="r">Stok Sistem</th><th class="r" style="width: 150px;">Stok Fisik</th><th class="r">Selisih</th></tr></thead>
                    <tbody>
                    @forelse($products as $p)
                        @php $sys = (float) $p->stock; @endphp
                        <tr x-data="{ f: '{{ old('counts.' . $p->id) }}' }" x-show="!q || '{{ strtolower(addslashes($p->name . ' ' . $p->sku)) }}'.includes(q.toLowerCase())">
                            <td>{{ $p->sku ?: '-' }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ optional($p->category)->name ?? '-' }}</td>
                            <td class="r">{{ $sys }} {{ $p->unit }}</td>
                            <td class="r"><input type="number" step="0.01" min="0" name="counts[{{ $p->id }}]" x-model="f" class="pay-amt" style="width: 120px;" placeholder="—"></td>
                            <td class="r" :style="f === '' ? '' : ((parseFloat(f) - {{ $sys }}) < 0 ? 'color: var(--danger); font-weight:700' : ((parseFloat(f) - {{ $sys }}) > 0 ? 'color:#15803d; font-weight:700' : ''))"
                                x-text="f === '' ? '' : ((v => (v > 0 ? '+' : '') + Math.round(v * 100) / 100)(parseFloat(f) - {{ $sys }}))"></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-empty">Belum ada barang bersetok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pay-foot">
                <span class="pay-total">Hanya barang yang kolom Stok Fisik-nya diisi dan berbeda yang disesuaikan.</span>
                <button type="submit" class="btn-primary" onclick="return confirm('Simpan hasil stok opname? Selisih akan disesuaikan dan dijurnal.')">Simpan Hasil Opname</button>
            </div>
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Penyesuaian dari Opname</h3><span class="sub">50 terakhir</span></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Tanggal</th><th>Barang</th><th class="r">Selisih</th><th class="r">Nilai</th><th>Keterangan</th><th>Jurnal</th><th></th></tr></thead>
                <tbody>
                @forelse($history as $m)
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
                                <form method="POST" action="{{ route('client.inventory.movements.cancel', $m->id) }}" style="display:inline" onsubmit="return confirm('Batalkan penyesuaian ini? Stok dikembalikan dan dibuat jurnal balik.')">@csrf
                                    <button class="pay-mini dng" type="submit">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="pay-empty">Belum ada stok opname.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
