{{-- Kolom No. Faktur Pajak di form dokumen baru. Param: $label (opsional), $hint --}}
<div class="fp-field">
    <label for="tax_invoice_number">{{ $label ?? 'No. Faktur Pajak' }}</label>
    <input type="text" name="tax_invoice_number" id="tax_invoice_number" value="{{ old('tax_invoice_number') }}" inputmode="numeric" maxlength="32" placeholder="Boleh diisi nanti">
    <p class="field-hint">{{ $hint }}</p>
</div>
