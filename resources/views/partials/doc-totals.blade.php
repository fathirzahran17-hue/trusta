{{--
    Ringkasan nilai dokumen. Elemen ber-id dipakai oleh skrip hitung di tiap form.
    Param: $taxes (opsional: bila ada, tampil Subtotal, Diskon, dan Pajak), $label (judul total), $showDiscount (default true bila ada $taxes)
--}}
<div class="doc-totals">
    @isset($taxes)
        <div class="doc-total-row">
            <span>Subtotal</span>
            <b id="displaySubtotal">0,00</b>
        </div>
        <div class="doc-total-row">
            <label for="discount_percent">Diskon</label>
            <span class="doc-total-input">
                <span class="input-affix"><input type="number" step="0.01" name="discount_percent" id="discount_percent" value="{{ old('discount_percent', 0) }}" min="0" max="100" onchange="calculateTotals()" onkeyup="calculateTotals()"><i>%</i></span>
                <b id="displayDiscount">0,00</b>
            </span>
        </div>
        <div class="doc-total-row">
            <label for="tax_id">Pajak</label>
            <span class="doc-total-input">
                <select name="tax_id" id="tax_id">
                    <option value="" data-rate="0">Tanpa Pajak</option>
                    @foreach($taxes as $tax)
                        <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected(old('tax_id') == $tax->id)>{{ $tax->name }} ({{ (float) $tax->rate }}%)</option>
                    @endforeach
                </select>
                <b id="displayTax">0,00</b>
            </span>
        </div>
    @endisset
    <div class="doc-total-row doc-total-grand">
        <span>{{ $label ?? 'Grand Total' }}</span>
        <b id="displayGrandTotal">0,00</b>
    </div>
</div>
