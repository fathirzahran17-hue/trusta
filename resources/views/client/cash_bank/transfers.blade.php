<x-app-layout>
    @section('title', 'Transfer Kas & Bank')
    @section('subtitle', 'Pindahkan dana antar rekening internal perusahaan')

    <div class="mb-4">
        <a href="{{ route('client.cash-bank.index') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Kas & Bank
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {!! session('error') !!}
        </div>
    @endif

    <form action="{{ route('client.cash-bank.transfers.store') }}" method="POST">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 14px; max-width: 800px;">
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Rincian Transfer</h3>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="from_account">Dari Rekening (Sumber) <span style="color:#f87171">*</span></label>
                        <select name="from_account" id="from_account" required>
                            <option value="">-- Pilih Akun Sumber --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('from_account') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="to_account">Ke Rekening (Tujuan) <span style="color:#f87171">*</span></label>
                        <select name="to_account" id="to_account" required>
                            <option value="">-- Pilih Akun Tujuan --</option>
                            @foreach($cashAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('to_account') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="transaction_date">Tanggal Transfer <span style="color:#f87171">*</span></label>
                        <input type="date" name="transaction_date" id="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label for="ref_number">No. Referensi (Otomatis jika kosong)</label>
                        <input type="text" name="ref_number" id="ref_number" value="{{ old('ref_number') }}">
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label for="amount_display">Jumlah Transfer (Rp) <span style="color:#f87171">*</span></label>
                    <input type="hidden" name="amount" id="amount" value="{{ old('amount', 0) }}">
                    <input type="text" id="amount_display" style="font-size: 1.2rem; font-weight: 700; color: #fff; padding: 10px; width: 100%; text-align: right;" required placeholder="0" onkeyup="formatRupiah(this, document.getElementById('amount'))" value="{{ old('amount') }}">
                </div>

                <div>
                    <label for="description">Keterangan Transfer</label>
                    <textarea name="description" id="description" rows="2" placeholder="Catatan... misal: Mutasi pengisian kas kecil">{{ old('description') }}</textarea>
                </div>
            </div>

            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Dana akan dipindahkan antar akun tanpa melibatkan biaya/pendapatan.
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-primary" style="background: rgba(59,130,246,0.2); color: #60a5fa; border-color: rgba(59,130,246,0.3);">
                        Proses Transfer
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function formatRupiah(element, hiddenElement) {
            let value = element.value.toString().replace(/[^,\d]/g, '');
            let split = value.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            element.value = rupiah;
            
            let realValue = value ? value.replace(/,/g, '.') : 0;
            if(hiddenElement) {
                hiddenElement.value = realValue;
            }
        }
        
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
