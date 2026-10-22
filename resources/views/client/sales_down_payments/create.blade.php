<x-app-layout>
    @section('title', 'Terima Uang Muka Penjualan')
    @section('subtitle', 'Pencatatan penerimaan uang muka yang menambah Kas/Bank')

    <div class="mb-4">
        <a href="{{ route('client.sales.down_payments') }}" class="text-trusta-teal hover:text-white flex items-center text-xs transition-colors">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Uang Muka
        </a>
    </div>

    @if(session('error'))
        <div class="mb-3 bg-red-500/10 border-l-3 border-red-500 p-3 text-red-400 rounded-r-lg text-xs">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('client.sales.down_payments.store') }}" method="POST">
        @csrf
        
        <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 14px; max-width: 800px;">
            <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 0.82rem; font-weight: 600; color: #fff; margin: 0;">Form Uang Muka</h3>
                    <div style="text-align: right;">
                        <span style="font-size: 0.65rem; color: #64748b; display: block;">No. Bukti</span>
                        <input type="text" name="payment_number" value="{{ $paymentNumber }}" style="font-size: 1rem; font-weight: 700; color: var(--trusta-teal); background: transparent; border: none; text-align: right; padding: 0; outline: none; width: 150px;" readonly>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="customer_id">Pelanggan <span style="color:#f87171">*</span></label>
                        <select name="customer_id" id="customer_id" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ old('customer_id') == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sales_order_id">Dari Pesanan (SO)</label>
                        <select name="sales_order_id" id="sales_order_id">
                            <option value="">-- Pilih SO (Opsional) --</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}" {{ old('sales_order_id') == $so->id ? 'selected' : '' }}>{{ $so->order_number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="account_id">Setor ke Rekening (Kas/Bank) <span style="color:#f87171">*</span></label>
                        <select name="account_id" id="account_id" required>
                            <option value="">-- Pilih Kas / Bank --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_header ? "disabled" : "" }} {{ old('account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment_date">Tanggal Bayar <span style="color:#f87171">*</span></label>
                        <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label for="amount_display">Jumlah Uang Muka (Rp) <span style="color:#f87171">*</span></label>
                    <input type="hidden" name="amount" id="amount" value="{{ old('amount', 0) }}">
                    <input type="text" id="amount_display" style="font-size: 1.2rem; font-weight: 700; color: #fff; padding: 10px;" required placeholder="0" onkeyup="formatRupiah(this, document.getElementById('amount'))" value="{{ old('amount') }}">
                </div>

                <div>
                    <label for="notes">Keterangan Tambahan</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Catatan pembayaran...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div style="padding: 10px 18px; background: rgba(30,41,59,0.4); border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.68rem; color: #475569;">
                    <span style="color: var(--trusta-teal); font-weight: 600;">INFO:</span> Akan menjurnal Debit Kas/Bank dan Kredit Piutang Usaha.
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('client.sales.down_payments') }}" style="padding: 6px 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #94a3b8; font-size: 0.78rem; font-weight: 500; text-decoration: none;">
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
