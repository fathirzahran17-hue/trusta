<x-app-layout>
    @section('title', 'Perintah Produksi')
    @section('subtitle', 'Olah bahan menjadi barang jadi: stok bahan berkurang, barang jadi bertambah, HPP produksi dijurnal otomatis')

    @php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 2, ',', '.'); @endphp

    <p class="pay-flow">
        Jurnal otomatis: <b>Debit</b> Persediaan Barang Jadi / <b>Kredit</b> Persediaan Bahan (nilai bahan) + <b>Kredit</b> akun biaya tambahan (mis. Biaya Tenaga Kerja / Overhead Dibebankan ke Produksi).
        Harga pokok barang jadi = (bahan + biaya tambahan) ÷ jumlah.
    </p>

    <form method="POST" action="{{ route('client.production.orders.store') }}" class="pay-card" x-data="prodForm()" x-init="init()">
        @csrf
        <div class="pay-card-head"><h3>Produksi Baru</h3><a class="pay-mini" href="{{ route('client.production.boms') }}">Kelola Resep</a></div>
        <div class="pay-form-grid">
            <div class="pay-field"><label>Tanggal *</label><input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required></div>
            <div class="pay-field" style="grid-column: span 2"><label>Pakai Resep</label>
                <select name="bom_id" x-model="bom" @change="applyBom()">
                    <option value="">-- Tanpa resep (isi bahan manual) --</option>
                    @foreach($boms as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select></div>
            <div class="pay-field" style="grid-column: span 2"><label>Barang Jadi *</label>
                <select name="product_id" x-model="product" required>
                    <option value="">-- Pilih barang jadi --</option>
                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->sku ? $p->sku . ' - ' : '' }}{{ $p->name }}</option>@endforeach
                </select></div>
            <div class="pay-field"><label>Jumlah Diproduksi *</label><input type="number" step="0.01" min="0.01" name="quantity" x-model="qty" @input="applyBom(true)" required></div>
            <div class="pay-field" style="grid-column: span 2"><label>Catatan</label><input type="text" name="notes" maxlength="255" value="{{ old('notes') }}"></div>
        </div>

        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Bahan Dipakai</th><th class="r">Stok</th><th class="r" style="width:150px">Jumlah</th><th class="r">Perkiraan Nilai</th><th style="width:40px"></th></tr></thead>
                <tbody>
                <template x-for="(r, i) in rows" :key="i">
                    <tr>
                        <td><select :name="'components[' + i + '][product_id]'" x-model="r.product_id" style="width:100%">
                            <option value="">-- Pilih bahan --</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->unit }})</option>@endforeach
                        </select></td>
                        <td class="r" :style="lack(r) ? 'color: var(--danger); font-weight:700' : ''" x-text="info(r.product_id) ? info(r.product_id).stock : ''"></td>
                        <td class="r"><input type="number" step="0.0001" min="0" class="pay-amt" style="width:130px" :name="'components[' + i + '][quantity]'" x-model="r.quantity"></td>
                        <td class="r" x-text="window.fmtRp((info(r.product_id) ? info(r.product_id).cost : 0) * (parseFloat(r.quantity) || 0))"></td>
                        <td class="c"><button type="button" class="pay-mini dng" @click="rows.splice(i, 1)" x-show="rows.length > 1">&times;</button></td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
        <div style="padding: 8px 18px;"><button type="button" class="pay-mini" @click="rows.push({ product_id: '', quantity: '' })">+ Tambah bahan</button></div>

        <div style="padding: 0 18px 12px;">
            <div class="pay-card-head" style="padding: 10px 0; border: 0"><h3>Biaya Tambahan (opsional)</h3><span class="sub">Tenaga kerja langsung, overhead pabrik, jasa maklon, dll.</span></div>
            <template x-for="(c, i) in costs" :key="'c' + i">
                <div style="display:grid; grid-template-columns: 2fr 2fr 1fr 32px; gap:8px; margin-bottom:8px;">
                    <select :name="'costs[' + i + '][account_id]'" x-model="c.account_id">
                        @foreach($costAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach
                    </select>
                    <input type="text" :name="'costs[' + i + '][description]'" x-model="c.description" placeholder="Keterangan, mis. upah borongan">
                    <input type="text" class="money-in" :name="'costs[' + i + '][amount]'" x-model="c.amount" placeholder="0,00" style="text-align:right">
                    <button type="button" class="pay-mini dng" @click="costs.splice(i, 1)">&times;</button>
                </div>
            </template>
            <button type="button" class="pay-mini" @click="costs.push({ account_id: '{{ optional($costAccounts->first())->id }}', amount: '', description: '' })">+ Tambah biaya</button>
        </div>

        <div class="pay-foot">
            <span class="pay-total">Perkiraan biaya produksi <b x-text="window.fmtRp(total())"></b> &middot; per unit <b x-text="window.fmtRp(qty > 0 ? total() / qty : 0)"></b></span>
            <button type="submit" class="btn-primary" onclick="return confirm('Simpan produksi? Stok bahan dikurangi dan barang jadi ditambah.')">Simpan Produksi</button>
        </div>
    </form>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Produksi</h3><span class="sub">{{ $orders->total() }} catatan</span></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Tanggal</th><th>No.</th><th>Barang Jadi</th><th class="r">Jumlah</th><th class="r">Total Biaya</th><th class="r">HPP / Unit</th><th>Jurnal</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($orders as $o)
                    <tr class="{{ $o->voided_at ? 'pay-void-row' : '' }}">
                        <td>{{ $o->date->format('d/m/Y') }}</td>
                        <td class="num"><a href="{{ route('client.production.orders.show', $o) }}">{{ $o->number }}</a></td>
                        <td>{{ optional($o->product)->name }}</td>
                        <td class="r">{{ (float) $o->quantity }} {{ optional($o->product)->unit }}</td>
                        <td class="r">{{ $rp($o->total_cost) }}</td>
                        <td class="r">{{ $rp($o->unit_cost) }}</td>
                        <td>@if($o->journal)<a class="lnk" href="{{ route('client.journals.show', $o->journal) }}">{{ $o->journal->ref_number }}</a>@else - @endif</td>
                        <td>@if($o->voided_at)<span class="st st-void" style="text-decoration:none">Dibatalkan</span>@else<span class="st st-paid">Selesai</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="pay-empty">Belum ada produksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div style="padding:10px 18px">{{ $orders->links() }}</div>@endif
    </div>

    <script>
        function prodForm() {
            const boms = @js($bomJson), prices = @js($priceJson);
            return {
                bom: '{{ old('bom_id') }}', product: '{{ old('product_id') }}', qty: '{{ old('quantity', 1) }}',
                rows: @js(array_values(old('components', [['product_id' => '', 'quantity' => '']]))),
                costs: @js(array_values(old('costs', []))),
                init() { if (this.bom && !@js((bool) old('components'))) this.applyBom(); },
                info(id) { return prices[id] || null; },
                lack(r) { const i = this.info(r.product_id); return i && (parseFloat(r.quantity) || 0) > i.stock + 0.0001; },
                applyBom(onlyQty) {
                    const b = boms.find(x => String(x.id) === String(this.bom)); if (!b) return;
                    if (!onlyQty) this.product = String(b.product_id);
                    const f = (parseFloat(this.qty) || 0) / (b.output || 1);
                    this.rows = b.items.map(i => ({ product_id: String(i.product_id), quantity: Math.round(i.quantity * f * 10000) / 10000 }));
                },
                total() {
                    const mat = this.rows.reduce((t, r) => t + ((this.info(r.product_id) || {}).cost || 0) * (parseFloat(r.quantity) || 0), 0);
                    const ex = this.costs.reduce((t, c) => t + (window.parseMoney ? window.parseMoney(c.amount) : parseFloat(c.amount) || 0), 0);
                    return Math.round((mat + ex) * 100) / 100;
                },
            };
        }
    </script>
</x-app-layout>
