<x-app-layout>
    @section('title', 'Transfer Kas & Bank')
    @section('subtitle', 'Pindahkan dana antar rekening internal perusahaan')

    <div class="mb-4">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Kas & Bank
        </a>
    </div>

    <form action="{{ route('client.cash-bank.transfers.store') }}" method="POST">
        @csrf
        
        <div class="doc-card doc-card-narrow">
            <div class="doc-section">
                <div class="doc-section-head">
                    <h3 class="doc-section-title">Rincian Transfer</h3>
                </div>

                <div class="form-grid form-cols-2">
                    <div>
                        <label for="from_account">Dari Rekening (Sumber) <span class="req">*</span></label>
                        <select name="from_account" id="from_account" required>
                            <option value="">-- Pilih Akun Sumber --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('from_account') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="to_account">Ke Rekening (Tujuan) <span class="req">*</span></label>
                        <select name="to_account" id="to_account" required>
                            <option value="">-- Pilih Akun Tujuan --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('to_account') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-grid form-cols-2">
                    <div>
                        <label for="transaction_date">Tanggal Transfer <span class="req">*</span></label>
                        <input type="date" name="transaction_date" id="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label for="ref_number">No. Referensi (Otomatis jika kosong)</label>
                        <input type="text" name="ref_number" id="ref_number" value="{{ old('ref_number') }}">
                    </div>
                </div>

                <div class="form-block">
                    <label for="amount_display">Jumlah Transfer (Rp) <span class="req">*</span></label>
                    <input type="hidden" name="amount" id="amount" value="{{ old('amount', 0) }}">
                    <input type="text" id="amount_display" class="input-lg" required placeholder="0" onkeyup="formatRupiah(this, document.getElementById('amount'))" value="{{ old('amount') }}">
                </div>

                <div>
                    <label for="description">Keterangan Transfer</label>
                    <textarea name="description" id="description" rows="2" placeholder="Catatan... misal: Mutasi pengisian kas kecil">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="doc-actions">
                <div class="doc-actions-info">
                    Dana akan dipindahkan antar akun tanpa melibatkan biaya/pendapatan.
                </div>
                <div class="doc-actions-buttons">
                    <button type="submit" class="btn-primary">
                        Proses Transfer
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
            
            // Validate same account
            document.querySelector('form').addEventListener('submit', function(e) {
                if (document.getElementById('from_account').value === document.getElementById('to_account').value) {
                    e.preventDefault();
                    alert('Rekening sumber dan tujuan tidak boleh sama!');
                }
            });
        });
    </script>
</x-app-layout>
