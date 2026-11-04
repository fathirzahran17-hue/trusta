{{-- PPN uang muka. Jumlah yang diketik sudah termasuk PPN. Param: $taxes, $hint --}}
<div class="form-grid form-cols-2" data-rounding="{{ auth()->user()->company?->tax_rounding }}">
    <div>
        <label for="tax_id">PPN</label>
        <select name="tax_id" id="tax_id">
            <option value="" data-rate="0">Tanpa PPN</option>
            @foreach($taxes as $tax)
                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected(old('tax_id') == $tax->id)>{{ str_contains($tax->name, '%') ? $tax->name : $tax->name . ' (' . (float) $tax->rate . '%)' }}</option>
            @endforeach
        </select>
        <p class="field-hint" id="dpTaxInfo">Pilih bila uang muka ini memakai Faktur Pajak. Jumlah di atas sudah termasuk PPN.</p>
    </div>
    @include('partials.tax-number-field', ['hint' => $hint])
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('tax_id'), display = document.getElementById('amount_display'), hidden = document.getElementById('amount'), info = document.getElementById('dpTaxInfo');
        var first = info.textContent;
        var fmt = function (n) { return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        function refresh() {
            var o = sel.options[sel.selectedIndex], rate = parseFloat(o.dataset.rate) || 0, gross = parseFloat(hidden.value) || 0;
            if (rate <= 0 || gross <= 0) { info.textContent = first; return; }
            var tax = window.LineTax.amount(gross / (1 + rate / 100), rate);
            info.textContent = 'Terdiri dari DPP Rp ' + fmt(gross - tax) + ' dan PPN Rp ' + fmt(tax) + '.';
        }
        sel.addEventListener('change', refresh);
        display.addEventListener('keyup', refresh);
        refresh();
    });
</script>
