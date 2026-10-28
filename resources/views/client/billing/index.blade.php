<x-app-layout>
    @section('title', 'Langganan')
    @section('subtitle', 'Status paket, perpanjangan, dan riwayat pembayaran TRUSTA')

    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $days = $current ? (int) now()->startOfDay()->diffInDays($current->end_date, false) : null;
        $pending = $payments->firstWhere('status', 'pending');
    @endphp

    <div class="rpt-summary">
        <div class="rpt-stat {{ $current ? 'good' : 'bad' }}">
            <div class="l">Status</div>
            <div class="v">{{ $current ? ($current->status === 'trial' ? 'Masa Trial' : 'Aktif') : ($company->is_premium ? 'Aktif (manual)' : 'Tidak Aktif') }}</div>
            <div style="font-size:.7rem;color:var(--text-dim)">{{ $current ? optional($current->plan)->name : 'Mode baca saja: data bisa dilihat, tidak bisa ditambah/diubah' }}</div>
        </div>
        <div class="rpt-stat {{ $days !== null && $days <= 7 ? 'bad' : '' }}">
            <div class="l">Berlaku sampai</div>
            <div class="v">{{ $current ? $current->end_date->format('d M Y') : '-' }}</div>
            @if($days !== null)<div style="font-size:.7rem;color:var(--text-dim)">{{ $days >= 0 ? 'sisa ' . $days . ' hari' : 'lewat ' . abs($days) . ' hari (masa toleransi ' . \App\Services\SubscriptionService::GRACE_DAYS . ' hari)' }}</div>@endif
        </div>
        <div class="rpt-stat">
            <div class="l">Pembayaran menunggu</div>
            <div class="v">{{ $pending ? $rp($pending->amount) : '-' }}</div>
            <div style="font-size:.7rem;color:var(--text-dim)">{{ $pending ? 'dikirim ' . $pending->created_at->format('d M Y H:i') . ', menunggu konfirmasi admin' : 'tidak ada' }}</div>
        </div>
    </div>

    @if($plans->isEmpty())
        <div class="pay-card"><div class="pay-empty">Belum ada paket yang ditawarkan. Hubungi admin TRUSTA.</div></div>
    @else
    <form method="POST" action="{{ route('client.billing.store') }}" enctype="multipart/form-data" x-data="{ plan: '{{ old('plan_id', optional(optional($current)->plan)->id ?? $plans->first()->id) }}', cycle: '{{ old('billing_cycle', 'monthly') }}', months: {{ (int) old('months', 1) }},
                prices: @js($plans->mapWithKeys(fn ($p) => [$p->id => ['m' => (float) $p->price, 'y' => $p->yearlyPrice()]])),
                get total() { const p = this.prices[this.plan]; return p ? (this.cycle === 'yearly' ? p.y : p.m * this.months) : 0; } }">
        @csrf
        <div class="pay-card">
            <div class="pay-card-head">
                <h3>Pilih Paket</h3>
                <div class="pay-tabs" style="margin:0">
                    <button type="button" class="pay-tab" :class="cycle === 'monthly' && 'on'" @click="cycle = 'monthly'">Bulanan</button>
                    <button type="button" class="pay-tab" :class="cycle === 'yearly' && 'on'" @click="cycle = 'yearly'">Tahunan <span class="n">hemat</span></button>
                </div>
            </div>
            <input type="hidden" name="billing_cycle" :value="cycle">
            <div class="plan-grid">
                @foreach($plans as $p)
                    @php $save = (float) $p->price * 12 - $p->yearlyPrice(); @endphp
                    <label class="plan-card" :class="plan == '{{ $p->id }}' && 'on'">
                        <input type="radio" name="plan_id" value="{{ $p->id }}" x-model="plan">
                        <div class="plan-name">{{ $p->name }} @if(optional(optional($current)->plan)->id === $p->id)<span class="st st-paid">Paket Anda</span>@endif</div>
                        @if($p->description)<div class="plan-desc">{{ $p->description }}</div>@endif
                        <div class="plan-price" x-show="cycle === 'monthly'">{{ $rp($p->price) }}<small>/bulan</small></div>
                        <div class="plan-price" x-show="cycle === 'yearly'" x-cloak>{{ $rp($p->yearlyPrice()) }}<small>/tahun</small></div>
                        @if($save > 0)<div class="plan-save" x-show="cycle === 'yearly'" x-cloak>Hemat {{ $rp($save) }} dibanding bulanan</div>@endif
                        <ul class="plan-feats">
                            <li>Hingga {{ $p->max_users }} pengguna</li>
                            @if($p->has_reports)<li>Laporan keuangan lengkap</li>@endif
                            @if($p->has_cash_bank)<li>Kas & bank, rekonsiliasi</li>@endif
                            @if($p->has_export)<li>Ekspor Excel / PDF</li>@endif
                            @if($p->has_auto_backup)<li>Backup otomatis</li>@endif
                            @foreach($p->featureList() as $f)<li>{{ $f }}</li>@endforeach
                        </ul>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="pay-card">
            <div class="pay-card-head"><h3>Bayar via Transfer</h3><span class="sub">Transfer sesuai nominal, lalu kirim bukti di bawah.</span></div>
            <div class="pay-form-grid">
                <div class="pay-field" style="grid-column: span 2">
                    <label>Rekening Tujuan</label>
                    <div style="white-space: pre-line; font-size:.85rem; color: var(--text-strong); line-height:1.5">{{ $bank ?: 'Rekening belum diatur — hubungi admin.' }}</div>
                    @if($note)<div style="font-size:.72rem; color: var(--text-dim); margin-top:4px; white-space: pre-line">{{ $note }}</div>@endif
                </div>
                <div class="pay-field" x-show="cycle === 'monthly'">
                    <label>Lama Langganan</label>
                    <select name="months" x-model.number="months">@foreach([1, 3, 6] as $m)<option value="{{ $m }}">{{ $m }} bulan</option>@endforeach</select>
                </div>
                <div class="pay-field">
                    <label>Total Transfer</label>
                    <div style="font-size:1.15rem; font-weight:800; color: var(--accent); padding-top:4px"
                         x-text="window.fmtRp(total)"></div>
                </div>
            </div>
            <div class="pay-form-grid" style="border-top: 1px solid var(--border)">
                <div class="pay-field"><label>Nama Pengirim *</label><input type="text" name="payer_name" value="{{ old('payer_name', $company->name) }}" required maxlength="120"></div>
                <div class="pay-field"><label>Bank Pengirim</label><input type="text" name="payer_bank" value="{{ old('payer_bank') }}" maxlength="80" placeholder="mis. BCA"></div>
                <div class="pay-field"><label>Tanggal Transfer *</label><input type="date" name="paid_at" value="{{ old('paid_at', date('Y-m-d')) }}" required></div>
                <div class="pay-field"><label>Bukti Transfer * <span style="text-transform:none;font-weight:400">(JPG/PNG/PDF, maks 2 MB)</span></label><input type="file" name="proof" accept="image/*,application/pdf" required></div>
                <div class="pay-field" style="grid-column: span 2"><label>Catatan</label><input type="text" name="notes" value="{{ old('notes') }}" maxlength="255"></div>
            </div>
            <div class="pay-foot">
                <span class="pay-total">@if($wa)Butuh bantuan? <a class="lnk" style="color:var(--accent)" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', preg_replace('/^0/', '62', $wa)) }}">WhatsApp admin</a>@else Langganan aktif setelah dikonfirmasi admin. @endif</span>
                <button type="submit" class="btn-primary" @disabled($pending)>{{ $pending ? 'Menunggu konfirmasi pembayaran sebelumnya' : 'Kirim Bukti Pembayaran' }}</button>
            </div>
        </div>
    </form>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Pembayaran</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Dikirim</th><th>Paket</th><th>Periode</th><th class="r">Jumlah</th><th>Status</th><th>Catatan Admin</th><th></th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->created_at->format('d M Y') }}</td>
                        <td>{{ optional($p->plan)->name }}</td>
                        <td>{{ $p->cycleLabel() }}</td>
                        <td class="r">{{ $rp($p->amount) }}</td>
                        <td>@php $st = ['pending' => ['Menunggu', 'st-partial'], 'approved' => ['Disetujui', 'st-paid'], 'rejected' => ['Ditolak', 'st-late']][$p->status] ?? [$p->status, '']; @endphp<span class="st {{ $st[1] }}">{{ $st[0] }}</span></td>
                        <td class="wrap">{{ $p->admin_note }}</td>
                        <td>@if($p->proof_path)<a class="lnk" target="_blank" href="{{ route('client.billing.proof', $p) }}">Bukti</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="pay-empty">Belum ada pembayaran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($history->isNotEmpty())
    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Langganan</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Paket</th><th>Mulai</th><th>Berakhir</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($history as $h)
                    <tr><td>{{ optional($h->plan)->name }}</td><td>{{ optional($h->start_date)->format('d M Y') }}</td><td>{{ optional($h->end_date)->format('d M Y') }}</td>
                        <td>{{ ['active' => 'Aktif', 'trial' => 'Trial', 'expired' => 'Berakhir', 'cancelled' => 'Dibatalkan'][$h->status] ?? $h->status }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</x-app-layout>
