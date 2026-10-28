<x-app-layout>
    @section('title', 'Resep Produk (BOM)')
    @section('subtitle', 'Daftar bahan untuk menghasilkan barang jadi — dipakai otomatis saat membuat Perintah Produksi')

    @php
        $rows = old('components', $edit ? $edit->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => (float) $i->quantity])->all() : [['product_id' => '', 'quantity' => '']]);
    @endphp

    <p class="pay-flow">Contoh: <b>1 Meja</b> = 4 Kaki Meja + 1 Papan + 12 Sekrup. Barang jadi & bahan harus <b>barang bersetok</b>; atur akun persediaannya (Bahan Baku / Barang Jadi) di master barang.</p>

    <form method="POST" action="{{ $edit ? route('client.production.boms.update', $edit) : route('client.production.boms.store') }}" class="pay-card" x-data="{ rows: @js(array_values($rows)) }">
        @csrf
        @if($edit) @method('PUT') @endif
        <div class="pay-card-head"><h3>{{ $edit ? 'Ubah Resep' : 'Resep Baru' }}</h3>@if($edit)<a href="{{ route('client.production.boms') }}" class="pay-mini">Batal ubah</a>@endif</div>
        <div class="pay-form-grid">
            <div class="pay-field" style="grid-column: span 2">
                <label>Barang Jadi *</label>
                <select name="product_id" required>
                    <option value="">-- Pilih barang jadi --</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', optional($edit)->product_id) == $p->id)>{{ $p->sku ? $p->sku . ' - ' : '' }}{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div class="pay-field"><label>Menghasilkan (jumlah) *</label><input type="number" step="0.01" min="0.01" name="output_qty" value="{{ old('output_qty', $edit ? (float) $edit->output_qty : 1) }}" required></div>
            <div class="pay-field" style="grid-column: span 2"><label>Nama Resep</label><input type="text" name="name" maxlength="150" value="{{ old('name', optional($edit)->name) }}" placeholder="Otomatis: Resep [nama barang]"></div>
        </div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Bahan</th><th class="r" style="width:160px">Jumlah</th><th style="width:40px"></th></tr></thead>
                <tbody>
                    <template x-for="(r, i) in rows" :key="i">
                        <tr>
                            <td>
                                <select :name="'components[' + i + '][product_id]'" x-model="r.product_id" style="width:100%">
                                    <option value="">-- Pilih bahan --</option>
                                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->sku ? $p->sku . ' - ' : '' }}{{ $p->name }} ({{ $p->unit }})</option>@endforeach
                                </select>
                            </td>
                            <td class="r"><input type="number" step="0.0001" min="0" class="pay-amt" :name="'components[' + i + '][quantity]'" x-model="r.quantity" style="width:140px"></td>
                            <td class="c"><button type="button" class="pay-mini dng" @click="rows.splice(i, 1)" x-show="rows.length > 1">&times;</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="pay-foot">
            <button type="button" class="pay-mini" @click="rows.push({ product_id: '', quantity: '' })">+ Tambah bahan</button>
            <button type="submit" class="btn-primary">{{ $edit ? 'Simpan Perubahan' : 'Simpan Resep' }}</button>
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Daftar Resep ({{ $boms->count() }})</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Resep</th><th>Barang Jadi</th><th class="r">Hasil</th><th>Bahan</th><th class="r">Perkiraan Biaya Bahan</th><th class="c">Aksi</th></tr></thead>
                <tbody>
                @forelse($boms as $b)
                    @php $est = $b->items->sum(fn ($i) => (float) $i->quantity * (float) optional($i->product)->buy_price); @endphp
                    <tr>
                        <td class="wrap"><b>{{ $b->name }}</b></td>
                        <td>{{ optional($b->product)->name }}</td>
                        <td class="r">{{ (float) $b->output_qty }} {{ optional($b->product)->unit }}</td>
                        <td class="wrap" style="font-size:.75rem">{{ $b->items->map(fn ($i) => (float) $i->quantity . ' ' . optional($i->product)->unit . ' ' . optional($i->product)->name)->implode(', ') }}</td>
                        <td class="r">Rp {{ number_format($est, 2, ',', '.') }}</td>
                        <td class="c" style="white-space:nowrap">
                            <a class="pay-mini" href="{{ route('client.production.boms.edit', $b) }}">Ubah</a>
                            <form method="POST" action="{{ route('client.production.boms.destroy', $b) }}" style="display:inline" onsubmit="return confirm('Hapus resep {{ addslashes($b->name) }}?')">@csrf @method('DELETE')<button class="pay-mini dng" type="submit">Hapus</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="pay-empty">Belum ada resep.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
