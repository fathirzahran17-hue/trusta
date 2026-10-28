{{-- Panel pembayaran faktur. Param: $invoice, $kind ('sales'|'purchase') --}}
@php
    $isSales = $kind === 'sales';
    $payments = $invoice->payments()->with(['account', 'journal'])->orderBy('payment_date')->orderBy('id')->get();
    $cashAccounts = \App\Models\Account::where('company_id', $invoice->company_id)->where('is_cash_bank', true)->orderBy('code')->get();
    $deductionAccounts = \App\Models\Account::where('company_id', $invoice->company_id)->where('is_settlement_deduction', true)->where('is_active', true)->where('normal_balance', $isSales ? 'debit' : 'credit')->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
    $payUrl = $isSales ? route('client.sales-invoices.payments.store', $invoice) : route('client.purchase-invoices.payments.store', $invoice);
    $dpApplications = \App\Models\DownPaymentApplication::with('downPayment')->where($isSales ? 'sales_invoice_id' : 'purchase_invoice_id', $invoice->id)->whereNull('voided_at')->orderBy('id')->get();
    $availableDps = \App\Models\DownPayment::where('company_id', $invoice->company_id)->where('type', $kind)
        ->where($isSales ? 'customer_id' : 'supplier_id', $isSales ? $invoice->customer_id : $invoice->supplier_id)
        ->orderBy('payment_date')->get()->filter(fn ($d) => $d->remaining > 0)->values();
    $dpUrl = $isSales ? route('client.sales-invoices.down-payments.apply', $invoice) : route('client.purchase-invoices.down-payments.apply', $invoice);
    $voidUrl = $isSales ? route('client.sales-invoices.void', $invoice) : route('client.purchase-invoices.void', $invoice);
@endphp
<div class="related-panel no-print">
    <div class="related-panel-head">
        <span>{{ $isSales ? 'Pembayaran dari Pelanggan' : 'Pembayaran ke Pemasok' }}</span>
        <span class="ri-meta">Dibayar Rp {{ number_format($invoice->amount_paid, 2, ',', '.') }} &bull; Sisa Rp {{ number_format($invoice->amount_due, 2, ',', '.') }}</span>
    </div>

    <div class="related-group">
        <div class="related-group-label">Riwayat</div>
        @forelse($payments as $p)
            <a href="{{ $p->journal_id ? route('client.journals.show', $p->journal_id) : '#' }}" class="related-item">
                <span><span class="ri-main">{{ $p->payment_number }}</span> &middot; {{ optional($p->account)->name }}</span>
                <span class="ri-meta">{{ $p->payment_date->format('d M Y') }} &bull; Rp {{ number_format($p->amount, 2, ',', '.') }}@if((float) $p->deduction_amount > 0) <span title="Bagian yang tidak lewat kas (potongan)">(potongan Rp {{ number_format($p->deduction_amount, 2, ',', '.') }})</span>@endif</span>
            </a>
        @empty
            <div class="related-empty">Belum ada pembayaran.</div>
        @endforelse
    </div>

    @if($dpApplications->isNotEmpty())
        <div class="related-group">
            <div class="related-group-label">Uang Muka Terpakai</div>
            @foreach($dpApplications as $a)
                <div class="related-item" style="justify-content:space-between;">
                    <span><span class="ri-main">{{ optional($a->downPayment)->payment_number }}</span> &middot; {{ $a->applied_date->format('d M Y') }}</span>
                    <span class="ri-meta" style="display:flex; gap:10px; align-items:center;">Rp {{ number_format($a->amount, 2, ',', '.') }}
                        <form method="POST" action="{{ route('client.down-payment-applications.void', $a) }}" style="display:inline" onsubmit="return confirm('Batalkan pemakaian uang muka ini?')">@csrf
                            <button type="submit" class="text-xs text-red-400 hover:underline">Batalkan</button>
                        </form>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if($invoice->status === 'void')
        <div class="related-group"><div class="related-empty">Faktur ini sudah dibatalkan.</div></div>
    @else
        @if($invoice->amount_due > 0 && $availableDps->isNotEmpty())
        <form method="POST" action="{{ $dpUrl }}" class="related-group" style="display:grid; gap:10px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); align-items:end;">
            @csrf
            <div style="grid-column: 1 / -1;" class="related-group-label">Pakai Uang Muka {{ $isSales ? 'dari pelanggan' : 'ke pemasok' }}</div>
            <div>
                <label class="related-group-label" for="dp_id">Uang Muka</label>
                <select name="down_payment_id" id="dp_id" required>
                    @foreach($availableDps as $d)
                        <option value="{{ $d->id }}" data-remaining="{{ $d->remaining }}">{{ $d->payment_number }} &middot; sisa Rp {{ number_format($d->remaining, 2, ',', '.') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="related-group-label" for="dp_date">Tanggal</label>
                <input type="date" name="applied_date" id="dp_date" value="{{ old('applied_date', date('Y-m-d')) }}" required>
            </div>
            <div>
                <label class="related-group-label" for="dp_amount">Jumlah dipakai (Rp)</label>
                <input type="text" inputmode="decimal" autocomplete="off" class="money-in" name="amount" id="dp_amount" value="{{ number_format(min((float) $invoice->amount_due, (float) $availableDps->first()->remaining), 2, ',', '.') }}" required>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors">Pakai Uang Muka</button>
            </div>
        </form>
        @endif

        @if($invoice->amount_due > 0)
        <form method="POST" action="{{ $payUrl }}" class="related-group" style="display:grid; gap:10px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); align-items:end;">
            @csrf
            <div>
                <label class="related-group-label" for="pay_account">Akun Kas/Bank</label>
                <select name="account_id" id="pay_account" required>
                    @foreach($cashAccounts as $a)
                        <option value="{{ $a->id }}" {{ old('account_id') == $a->id ? 'selected' : '' }}>{{ $a->code }} - {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="related-group-label" for="pay_date">Tanggal</label>
                <input type="date" name="payment_date" id="pay_date" value="{{ old('payment_date', date('Y-m-d')) }}" required>
            </div>
            <div>
                <label class="related-group-label" for="pay_amount">Jumlah dilunasi (Rp)</label>
                <input type="text" inputmode="decimal" autocomplete="off" class="money-in" data-max="{{ (float) $invoice->amount_due }}" name="amount" id="pay_amount" value="{{ old('amount', number_format((float) $invoice->amount_due, 2, ',', '.')) }}" required>
            </div>
            <div>
                <label class="related-group-label" for="pay_notes">Catatan</label>
                <input type="text" name="notes" id="pay_notes" value="{{ old('notes') }}" placeholder="Opsional">
            </div>
            @include('partials.payment-split', ['deductionAccounts' => $deductionAccounts, 'sales' => $isSales, 'totalInput' => '#pay_amount'])
            <div>
                <button type="submit" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors">{{ $isSales ? 'Terima Pembayaran' : 'Catat Pembayaran' }}</button>
            </div>
        </form>
        <script>
            (function () {
                var a = document.getElementById('pay_amount'); if (!a) return;
                a.addEventListener('input', function () { var m = parseFloat(a.dataset.max); if (window.parseMoney(a.value) > m) { a.value = window.fmtMoney(m); a.dispatchEvent(new Event('moneyfix')); } });
            })();
        </script>
        @endif

        @if((float) $invoice->amount_paid <= 0)
        <form method="POST" action="{{ $voidUrl }}" class="related-group" onsubmit="return confirm('Batalkan faktur ini? Jurnal balik akan dibuat dan faktur tidak bisa dipakai lagi.');">
            @csrf
            <button type="submit" class="text-xs text-red-400 hover:underline">Batalkan faktur ini</button>
        </form>
        @endif
    @endif
</div>
