{{--
    Ringkasan nilai dokumen. Elemen ber-id dipakai oleh skrip hitung di tiap form.
    Param: $taxes (opsional: bila ada, tampil Subtotal, Diskon, dan Pajak), $label (judul total), $returnTax (opsional: rincian di form retur),
    $editDiscPct dan $editTaxId (opsional: nilai dokumen yang diubah atau pajak pesanan asal), $taxFromSource (opsional: baris ditarik dari dokumen lain)
--}}
@php
    $taxCompany = auth()->user()?->company;
    if (isset($taxes)) {
        // Dokumen baru: pajak dengan tarif default di Preferensi langsung terpilih. Dokumen yang diubah atau ditarik mengikuti asalnya.
        $defaultRate = (float) ($taxCompany?->default_tax_rate ?? 0);
        $followsSource = isset($order) || !empty($taxFromSource);
        $defaultTaxId = ($followsSource || $defaultRate <= 0) ? null : optional($taxes->first(fn ($t) => abs((float) $t->rate - $defaultRate) < 0.005))->id;
        $presetTax = old('tax_id', $editTaxId ?? $defaultTaxId);
    }
@endphp
<div class="doc-totals" data-rounding="{{ $taxCompany?->tax_rounding }}">
    @isset($taxes)
        <div class="doc-total-row">
            <span>Subtotal</span>
            <b id="displaySubtotal">0,00</b>
        </div>
        <div class="doc-total-row">
            <label for="discount_percent">Diskon</label>
            <span class="doc-total-input">
                <span class="input-affix"><input type="number" step="0.01" name="discount_percent" id="discount_percent" value="{{ old('discount_percent', $editDiscPct ?? 0) }}" min="0" max="100" onchange="calculateTotals()" onkeyup="calculateTotals()"><i>%</i></span>
                <b id="displayDiscount">0,00</b>
            </span>
        </div>
        <div class="doc-total-row" id="rowTaxBase" hidden>
            <span>DPP (baris kena PPN)</span>
            <b id="displayTaxBase">0,00</b>
        </div>
        <div class="doc-total-row">
            <label for="tax_id">Pajak</label>
            <span class="doc-total-input">
                <select name="tax_id" id="tax_id">
                    <option value="" data-rate="0">Tanpa Pajak</option>
                    @foreach($taxes as $tax)
                        <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected($presetTax == $tax->id)>{{ str_contains($tax->name, '%') ? $tax->name : $tax->name . ' (' . (float) $tax->rate . '%)' }}</option>
                    @endforeach
                </select>
                <b id="displayTax">0,00</b>
            </span>
        </div>
    @endisset
    @isset($returnTax)
        <div class="doc-total-row">
            <span>Nilai barang</span>
            <b id="displaySubtotal">0,00</b>
        </div>
        <div class="doc-total-row" id="rowReturnDiscount" hidden>
            <span>Diskon faktur</span>
            <b id="displayDiscount">0,00</b>
        </div>
        <div class="doc-total-row" id="rowReturnTax" hidden>
            <span>PPN</span>
            <b id="displayTax">0,00</b>
        </div>
    @endisset
    <div class="doc-total-row doc-total-grand">
        <span>{{ $label ?? 'Grand Total' }}</span>
        <b id="displayGrandTotal">0,00</b>
    </div>
</div>
