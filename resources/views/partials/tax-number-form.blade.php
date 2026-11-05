{{-- Mengisi atau mengubah No. Faktur Pajak dokumen yang sudah tersimpan. Param: $action, $number, $label (opsional) --}}
<form method="POST" action="{{ $action }}" class="fp-panel no-print" data-no-guard>
    @csrf
    <div class="fp-panel-field">
        <label for="tax_invoice_number">{{ $label ?? 'No. Faktur Pajak' }}</label>
        <input type="text" name="tax_invoice_number" id="tax_invoice_number" value="{{ old('tax_invoice_number', $number) }}" inputmode="numeric" maxlength="32" placeholder="Sesuai dokumen Faktur Pajak">
    </div>
    <button type="submit" class="btn-secondary">Simpan Nomor</button>
    <p class="field-hint">Dipakai di Laporan PPN. Mengisinya tidak mengubah jurnal, jadi tetap bisa setelah tutup buku.</p>
</form>
