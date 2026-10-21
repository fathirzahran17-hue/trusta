{{-- Form Barang / Jasa. Param: $kind ('goods'|'service'), $product (opsional), $categories, $units, $revenueAccounts, $costAccounts, $defaultRevenueId, $defaultCostId, $nextSku --}}
@php
    $isService = $kind === 'service';
    $p = $product ?? null;
    $val = fn ($k, $d = null) => old($k, $p ? $p->{$k} : $d);
    $revSel = old('revenue_account_id', $p->revenue_account_id ?? $defaultRevenueId);
    $costSel = old('expense_account_id', $p->expense_account_id ?? $defaultCostId);
    $sub = old('is_subcontract', $p ? ($p->expense_account_id ? 1 : 0) : 0);
    $unitNow = old('unit', $p->unit ?? ($isService ? 'kali' : 'pcs'));
    $serviceUnits = ['kali' => 'Kali', 'jam' => 'Jam', 'hari' => 'Hari', 'bulan' => 'Bulan', 'paket' => 'Paket', 'unit' => 'Unit'];
    $goodsUnits = ['pcs' => 'Pcs', 'unit' => 'Unit', 'dus' => 'Dus', 'kg' => 'Kg', 'liter' => 'Liter', 'meter' => 'Meter', 'set' => 'Set'];
    $baseUnits = $isService ? $serviceUnits : $goodsUnits;
    $custom = $units->pluck('name', 'abbreviation')->all();   // satuan buatan sendiri (Satuan Barang)
@endphp

<input type="hidden" name="type" value="{{ $kind }}">

<div class="pay-card">
    <div class="pay-card-head"><h3>Informasi {{ $isService ? 'Jasa' : 'Barang' }}</h3><span class="sub">{{ $isService ? 'Layanan yang Anda jual. Tidak memiliki stok.' : 'Barang dagangan yang dikelola stoknya.' }}</span></div>
    <div class="pay-form-grid">
        <div class="pay-field">
            <label for="sku">Kode <span style="color:var(--danger)">*</span></label>
            <input type="text" name="sku" id="sku" value="{{ $val('sku', $nextSku) }}" required maxlength="50">
            @error('sku')<div style="color:var(--danger);font-size:.72rem;margin-top:3px">{{ $message }}</div>@enderror
        </div>
        <div class="pay-field" style="grid-column: span 2">
            <label for="name">Nama {{ $isService ? 'Jasa' : 'Barang' }} <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name" id="name" value="{{ $val('name') }}" required maxlength="255" placeholder="{{ $isService ? 'Contoh: Jasa Instalasi AC' : 'Contoh: Laptop ASUS VivoBook' }}">
            @error('name')<div style="color:var(--danger);font-size:.72rem;margin-top:3px">{{ $message }}</div>@enderror
        </div>
        <div class="pay-field">
            <label for="category_id">Kategori</label>
            <select name="category_id" id="category_id">
                <option value="">Tanpa kategori</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $p->category_id ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="pay-field">
            <label for="unit">Satuan</label>
            <select name="unit" id="unit">
                @foreach($baseUnits as $k => $label)<option value="{{ $k }}" @selected(strtolower($unitNow) === $k)>{{ $label }}</option>@endforeach
                @foreach($custom as $abbr => $name)
                    @if(!isset($baseUnits[strtolower($abbr)]))<option value="{{ $abbr }}" @selected($unitNow === $abbr)>{{ $name }} ({{ $abbr }})</option>@endif
                @endforeach
                @if($unitNow && !isset($baseUnits[strtolower($unitNow)]) && !isset($custom[$unitNow]))<option value="{{ $unitNow }}" selected>{{ $unitNow }}</option>@endif
            </select>
        </div>
    </div>
</div>

<div class="pay-card">
    <div class="pay-card-head"><h3>{{ $isService ? 'Nilai Jasa & Pendapatan' : 'Harga & Pendapatan' }}</h3><span class="sub">Pendapatan dicatat otomatis ke akun di bawah saat faktur penjualan dibuat.</span></div>
    <div class="pay-form-grid">
        <div class="pay-field">
            <label for="sell_price">{{ $isService ? 'Tarif Acuan (Rp) — opsional' : 'Harga Jual (Rp)' }} @unless($isService)<span style="color:var(--danger)">*</span>@endunless</label>
            <input type="number" step="0.01" min="0" name="sell_price" id="sell_price" value="{{ $val('sell_price', 0) }}" @unless($isService) required @endunless>
            @if($isService)<div style="font-size:.7rem;color:var(--text-dim);margin-top:3px;line-height:1.4">Nilai jasa berbeda di tiap pekerjaan, jadi tidak ada harga tetap. Isi 0 bila tidak ada acuan — nilai sebenarnya diisi pada pesanan/faktur.</div>@endif
            @error('sell_price')<div style="color:var(--danger);font-size:.72rem;margin-top:3px">{{ $message }}</div>@enderror
        </div>
        @unless($isService)
        <div class="pay-field">
            <label for="buy_price">Harga Beli (Rp)</label>
            <input type="number" step="0.01" min="0" name="buy_price" id="buy_price" value="{{ $val('buy_price', 0) }}">
            <div style="font-size:.7rem;color:var(--text-dim);margin-top:3px">Harga pokok akan dihitung otomatis (rata-rata) dari pembelian.</div>
        </div>
        @endunless
        <div class="pay-field" style="grid-column: span 2">
            <label for="revenue_account_id">Akun Pendapatan</label>
            <select name="revenue_account_id" id="revenue_account_id" class="searchable-select">
                @foreach($revenueAccounts as $a)<option value="{{ $a->id }}" @selected($revSel == $a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach
            </select>
            <div style="font-size:.7rem;color:var(--text-dim);margin-top:3px">Sudah terisi otomatis ({{ $isService ? 'Pendapatan Jasa' : 'Pendapatan Penjualan' }}). Ubah hanya bila perlu.</div>
        </div>
    </div>
</div>

@if($isService)
<div class="pay-card">
    <div class="pay-card-head"><h3>Biaya Subkontraktor <span style="font-weight:500;text-transform:none;letter-spacing:0">(opsional)</span></h3>
        <span class="sub">Jasa murni tidak punya HPP. Aktifkan hanya bila pekerjaannya diserahkan ke pihak lain.</span></div>
    <div style="padding:14px 18px">
        <label style="display:flex;gap:10px;align-items:center;cursor:pointer;font-size:.85rem;color:var(--text-strong)">
            <input type="hidden" name="is_subcontract" value="0">
            <input type="checkbox" name="is_subcontract" value="1" id="is_subcontract" @checked($sub) style="width:17px;height:17px;accent-color:var(--accent)">
            Jasa ini memakai subkontraktor (ada biaya pembelian)
        </label>
    </div>
    <div class="pay-form-grid" id="subFields" style="{{ $sub ? '' : 'display:none' }};padding-top:0">
        <div class="pay-field">
            <label for="buy_price">Biaya Subkontrak Standar (Rp)</label>
            <input type="number" step="0.01" min="0" name="buy_price" id="buy_price" value="{{ $val('buy_price', 0) }}">
        </div>
        <div class="pay-field" style="grid-column: span 2">
            <label for="expense_account_id">Akun Biaya Subkontrak</label>
            <select name="expense_account_id" id="expense_account_id" class="searchable-select">
                <option value="">Otomatis: HPP - Jasa Subkontrak</option>
                @foreach($costAccounts as $a)<option value="{{ $a->id }}" @selected($costSel == $a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach
            </select>
        </div>
    </div>
</div>
<script>
    (function () {
        var cb = document.getElementById('is_subcontract'), box = document.getElementById('subFields');
        if (cb && box) cb.addEventListener('change', function () { box.style.display = cb.checked ? '' : 'none'; });
    })();
</script>
@else
<div class="pay-card">
    <details {{ $errors->has('expense_account_id') ? 'open' : '' }}>
        <summary style="cursor:pointer;padding:13px 18px;font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-strong)">Pengaturan akun lanjutan <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--text-dim)">(sudah otomatis)</span></summary>
        <div class="pay-form-grid" style="padding-top:0">
            <div class="pay-field" style="grid-column: span 2">
                <label for="expense_account_id">Akun HPP</label>
                <select name="expense_account_id" id="expense_account_id" class="searchable-select">
                    @foreach($costAccounts as $a)<option value="{{ $a->id }}" @selected($costSel == $a->id)>{{ $a->code }} - {{ $a->name }}</option>@endforeach
                </select>
                <div style="font-size:.7rem;color:var(--text-dim);margin-top:3px">Dipakai saat barang terjual. Persediaan otomatis memakai akun Persediaan.</div>
            </div>
        </div>
    </details>
</div>
@endif

@if($p)
<div class="pay-card"><div style="padding:14px 18px">
    <label style="display:flex;gap:10px;align-items:center;cursor:pointer;font-size:.85rem;color:var(--text-strong)">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p->is_active)) style="width:17px;height:17px;accent-color:var(--accent)"> Aktif (bisa dipilih di transaksi)
    </label>
</div></div>
@endif
