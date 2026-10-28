<x-app-layout>
    @section('title', 'Pengaturan Platform')
    @section('subtitle', 'Rekening pembayaran, kontak admin, dan trial otomatis untuk pendaftar baru')

    <form method="POST" action="{{ route('superadmin.settings.update') }}">
        @csrf
        <div class="pay-card">
            <div class="pay-card-head"><h3>Pembayaran</h3><span class="sub">Ditampilkan di halaman Langganan klien</span></div>
            <div class="pay-form-grid">
                <div class="pay-field" style="grid-column: span 2">
                    <label>Rekening Tujuan Transfer</label>
                    <textarea name="bank_accounts" rows="4" maxlength="1000" placeholder="BCA 1234567890 a.n. PT Trusta Indonesia&#10;Mandiri 0987654321 a.n. PT Trusta Indonesia">{{ old('bank_accounts', $settings['bank_accounts']) }}</textarea>
                </div>
                <div class="pay-field" style="grid-column: span 2">
                    <label>Catatan untuk Klien</label>
                    <textarea name="billing_note" rows="2" maxlength="500" placeholder="mis. Konfirmasi diproses jam kerja (Senin–Jumat 09.00–17.00)">{{ old('billing_note', $settings['billing_note']) }}</textarea>
                </div>
                <div class="pay-field"><label>WhatsApp Admin</label><input type="text" name="admin_whatsapp" value="{{ old('admin_whatsapp', $settings['admin_whatsapp']) }}" placeholder="0851xxxxxxxx"></div>
                <div class="pay-field"><label>Email Admin</label><input type="email" name="admin_email" value="{{ old('admin_email', $settings['admin_email']) }}"></div>
            </div>
        </div>

        <div class="pay-card">
            <div class="pay-card-head"><h3>Trial Otomatis</h3><span class="sub">Pendaftar baru langsung bisa mencoba tanpa menunggu admin</span></div>
            <div class="pay-form-grid">
                <div class="pay-field"><label>Lama Trial (hari)</label><input type="number" name="trial_days" min="0" max="90" value="{{ old('trial_days', $settings['trial_days'] ?? 0) }}"><div style="font-size:.7rem;color:var(--text-dim);margin-top:3px">0 = tanpa trial (pendaftar baru hanya bisa melihat sampai diaktifkan).</div></div>
                <div class="pay-field" style="grid-column: span 2"><label>Paket Trial</label>
                    <select name="trial_plan_id"><option value="">Paket termurah yang aktif</option>@foreach($plans as $p)<option value="{{ $p->id }}" @selected(old('trial_plan_id', $settings['trial_plan_id']) == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
            </div>
            <div class="pay-foot">
                <span class="pay-total">Langganan yang lewat tanggal berakhir otomatis menjadi "Berakhir" setelah masa toleransi {{ \App\Services\SubscriptionService::GRACE_DAYS }} hari (klien tetap bisa melihat data).</span>
                <button type="submit" class="btn-primary">Simpan Pengaturan</button>
            </div>
        </div>
    </form>
</x-app-layout>
