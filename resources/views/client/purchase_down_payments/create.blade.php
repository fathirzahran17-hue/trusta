<x-app-layout>
    @section('title', 'Bayar Uang Muka Pembelian')
    @section('subtitle', 'Pencatatan pembayaran uang muka yang mengurangi Kas/Bank')

    <div class="mb-4">
        <a href="{{ route('client.purchases.down_payments') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Uang Muka
        </a>
    </div>

    <form action="{{ route('client.purchases.down_payments.store') }}" method="POST">
        @csrf
        
        <div class="doc-card doc-card-narrow">
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Form Uang Muka</h3>
                    <div class="doc-number">
                        <span class="doc-number-label">No. Bukti</span>
                        <input type="text" name="payment_number" value="{{ $paymentNumber }}" class="doc-number-input" readonly>
                    </div>
                </div>

                <div class="form-grid form-cols-2">
                    <div>
                        <label for="supplier_id">Pemasok <span class="req">*</span></label>
                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">-- Pilih Pemasok --</option>
                            @foreach($suppliers as $supp)
                                <option value="{{ $supp->id }}" {{ old('supplier_id') == $supp->id ? 'selected' : '' }}>{{ $supp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purchase_order_id">Dari Pesanan (PO)</label>
                        <select name="purchase_order_id" id="purchase_order_id">
                            <option value="">-- Pilih PO (Opsional) --</option>
                            @foreach($purchaseOrders as $po)
                                <option value="{{ $po->id }}" {{ old('purchase_order_id') == $po->id ? 'selected' : '' }}>{{ $po->po_number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-grid form-cols-2">
                    <div>
                        <label for="account_id">Bayar dari Rekening (Kas/Bank) <span class="req">*</span></label>
                        <select name="account_id" id="account_id" required>
                            <option value="">-- Pilih Kas / Bank --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment_date">Tanggal Bayar <span class="req">*</span></label>
                        <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="form-block">
                    <label for="amount_display">Jumlah Uang Muka (Rp) <span class="req">*</span></label>
                    <input type="hidden" name="amount" id="amount" value="{{ old('amount', 0) }}">
                    <input type="text" id="amount_display" class="input-lg" required placeholder="0" onkeyup="formatRupiah(this, document.getElementById('amount'))" value="{{ old('amount') }}">
                </div>

                @include('partials.down-payment-tax', ['taxes' => $taxes, 'hint' => 'Sesuai Faktur Pajak dari pemasok. Bisa diisi nanti di daftar uang muka.'])

                <div>
                    <label for="notes">Keterangan Tambahan</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Catatan pembayaran...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="doc-actions">
                <div class="doc-actions-info">
                    Akan menjurnal Debit Uang Muka Pembelian, serta Pajak Masukan bila memakai PPN, dan Kredit Kas/Bank.
                </div>
                <div class="doc-actions-buttons">
                    <a href="{{ route('client.purchases.down_payments') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary">
                        Simpan Uang Muka
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        
        // init
        document.addEventListener('DOMContentLoaded', function() {
            let displayInput = document.getElementById('amount_display');
            let hiddenInput = document.getElementById('amount');
            if (hiddenInput.value > 0) {
                displayInput.value = hiddenInput.value;
                formatRupiah(displayInput, hiddenInput);
            }
        });
    </script>
</x-app-layout>
