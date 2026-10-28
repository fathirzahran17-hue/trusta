<x-app-layout>
    @section('title', 'Klien')
    @section('subtitle', 'Perusahaan terdaftar, status langganan, dan tindakan admin')

    @php
        $stateLabel = ['active' => ['Aktif', 'st-paid'], 'trial' => ['Trial', 'st-partial'], 'inactive' => ['Tidak aktif', 'st-void']];
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    @endphp

    <div x-data="clientAdmin()">
        <div class="pay-tabs">
            @foreach(['all' => 'Semua', 'active' => 'Aktif', 'trial' => 'Trial', 'expiring' => 'Berakhir ≤ 14 hari', 'inactive' => 'Tidak aktif'] as $k => $l)
                <a href="{{ route('superadmin.clients.index', array_filter(['tab' => $k, 'q' => $q])) }}" class="pay-tab {{ $tab === $k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>
            @endforeach
            <span style="flex:1"></span>
            <form method="GET" action="{{ route('superadmin.clients.index') }}" style="display:flex; gap:6px">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama / email…" style="padding:6px 10px; font-size:.8rem; min-width:220px">
            </form>
        </div>

        <div class="pay-card">
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>Perusahaan</th><th>Bidang</th><th class="c">Pengguna</th><th>Paket</th><th>Berlaku s/d</th><th>Status</th><th class="c">Aksi</th></tr></thead>
                    <tbody>
                    @forelse($clients as $c)
                        @php [$sl, $sc] = $stateLabel[$c->state]; @endphp
                        <tr>
                            <td class="wrap">
                                <b style="color: var(--text-strong)">{{ $c->name }}</b>
                                @if(isset($pendingByCompany[$c->id]))<a href="{{ route('superadmin.payments.index') }}" class="st st-late" style="margin-left:6px; text-decoration:none">Pembayaran menunggu</a>@endif
                                <div style="font-size:.72rem; color: var(--text-dim)">{{ optional($c->owner)->name }} · {{ $c->email ?: optional($c->owner)->email ?: '-' }} · daftar {{ $c->created_at->format('d M Y') }}</div>
                            </td>
                            <td>{{ \App\Support\BusinessType::label($c->business_type) }}</td>
                            <td class="c">{{ $c->users_count }}</td>
                            <td>{{ optional(optional($c->cur)->plan)->name ?? '-' }}</td>
                            <td>
                                @if($c->cur)
                                    {{ $c->cur->end_date->format('d M Y') }}
                                    <div style="font-size:.7rem; color: {{ $c->days_left !== null && $c->days_left <= 14 ? 'var(--danger)' : 'var(--text-dim)' }}">{{ $c->days_left >= 0 ? 'sisa ' . $c->days_left . ' hari' : 'lewat ' . abs($c->days_left) . ' hari' }}</div>
                                @else - @endif
                            </td>
                            <td><span class="st {{ $sc }}" style="text-decoration:none">{{ $sl }}</span></td>
                            <td class="c"><button type="button" class="pay-mini prim" @click="manage(@js(['id' => $c->id, 'name' => $c->name, 'premium' => (bool) $c->is_premium, 'hasSub' => (bool) $c->subscriptions->first(), 'status' => optional($c->subscriptions->first())->status, 'end' => optional(optional($c->subscriptions->first())->end_date)->format('Y-m-d'), 'plan' => optional($c->cur)->plan_id, 'owner' => optional($c->owner)->email]))">Kelola</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pay-empty">Tidak ada klien di sini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Panel kelola klien --}}
        <div class="sa-modal" x-show="c" x-cloak @keydown.escape.window="c = null">
            <div class="sa-modal-bg" @click="c = null"></div>
            <div class="sa-modal-box pay-card" style="margin:0">
                <div class="pay-card-head"><h3 x-text="c && c.name"></h3><button type="button" class="pay-mini" @click="c = null">Tutup</button></div>
                <div class="sa-modal-tabs pay-tabs" style="padding: 12px 18px 0; margin:0">
                    <button type="button" class="pay-tab" :class="t === 'grant' && 'on'" @click="t = 'grant'">Aktifkan / Perpanjang</button>
                    <button type="button" class="pay-tab" :class="t === 'edit' && 'on'" @click="t = 'edit'" x-show="c && c.hasSub">Ubah Langganan</button>
                    <button type="button" class="pay-tab" :class="t === 'pw' && 'on'" @click="t = 'pw'">Reset Password</button>
                    <button type="button" class="pay-tab" :class="t === 'del' && 'on'" @click="t = 'del'">Hapus</button>
                </div>

                <form method="POST" :action="'{{ url('superadmin/clients') }}/' + (c && c.id) + '/grant-premium'" x-show="t === 'grant'">
                    @csrf
                    <div class="pay-form-grid">
                        <div class="pay-field" style="grid-column: span 2"><label>Paket</label>
                            <select name="plan_id" required>@foreach($plans as $p)<option value="{{ $p->id }}" :selected="c && c.plan == {{ $p->id }}">{{ $p->name }} — {{ $rp($p->price) }}/bln</option>@endforeach</select></div>
                        <div class="pay-field"><label>Jenis</label><select name="status"><option value="active">Berbayar</option><option value="trial">Trial</option></select></div>
                        <div class="pay-field"><label>Lama (bulan)</label><input type="number" name="months" min="1" max="36" value="1" required></div>
                        <div class="pay-field"><label>Jumlah Dibayar (Rp)</label><input type="text" name="amount_paid" class="money-in" placeholder="0,00"></div>
                        <div class="pay-field" style="grid-column: span 2"><label>Catatan</label><input type="text" name="notes" maxlength="255" placeholder="mis. bayar tunai / promo"></div>
                    </div>
                    <div class="pay-foot"><span class="pay-total">Bila masih ada langganan berbayar berjalan, periode baru disambung setelahnya.</span><button type="submit" class="btn-primary">Aktifkan</button></div>
                </form>

                <form method="POST" :action="'{{ url('superadmin/clients') }}/' + (c && c.id) + '/subscription'" x-show="t === 'edit'">
                    @csrf @method('PUT')
                    <div class="pay-form-grid">
                        <div class="pay-field"><label>Status</label>
                            <select name="status" x-model="edit.status"><option value="active">Aktif</option><option value="trial">Trial</option><option value="expired">Berakhir</option><option value="cancelled">Dibatalkan</option></select></div>
                        <div class="pay-field"><label>Berlaku sampai</label><input type="date" name="end_date" x-model="edit.end" required></div>
                    </div>
                    <div class="pay-foot"><span class="pay-total">Mengubah langganan terakhir klien ini.</span><button type="submit" class="btn-primary">Simpan</button></div>
                    <div style="padding: 0 18px 16px" x-show="c && c.premium">
                        <button type="submit" form="revokeForm" class="pay-mini dng" onclick="return confirm('Hentikan langganan sekarang? Klien menjadi mode baca.')">Hentikan langganan sekarang</button>
                    </div>
                </form>
                <form id="revokeForm" method="POST" :action="'{{ url('superadmin/clients') }}/' + (c && c.id) + '/revoke-premium'">@csrf</form>

                <form method="POST" :action="'{{ url('superadmin/clients') }}/' + (c && c.id) + '/reset-password'" x-show="t === 'pw'">
                    @csrf
                    <div class="pay-form-grid">
                        <div class="pay-field" style="grid-column: span 2"><label>Akun utama</label><div style="padding-top:6px; color: var(--text-strong)" x-text="c && c.owner"></div></div>
                        <div class="pay-field" style="grid-column: span 2"><label>Password Baru (min. 8)</label><input type="text" name="new_password" minlength="8" required autocomplete="off"></div>
                    </div>
                    <div class="pay-foot"><span class="pay-total">Sampaikan password baru ke pemilik akun secara pribadi.</span><button type="submit" class="btn-primary">Reset Password</button></div>
                </form>

                <form method="POST" :action="'{{ url('superadmin/clients') }}/' + (c && c.id)" x-show="t === 'del'" @submit="if (confirmName !== (c && c.name)) { $event.preventDefault(); alert('Ketik nama perusahaan persis untuk konfirmasi.'); }">
                    @csrf @method('DELETE')
                    <div style="padding: 16px 18px; font-size:.82rem; color: var(--text)">
                        <p style="margin:0 0 10px; color: var(--danger); font-weight:700">Semua data perusahaan ini (transaksi, akun, pengguna) dihapus permanen.</p>
                        <label class="pay-field"><span style="font-size:.7rem; color: var(--text-dim)">Ketik nama perusahaan untuk konfirmasi</span><input type="text" x-model="confirmName" autocomplete="off"></label>
                    </div>
                    <div class="pay-foot"><span></span><button type="submit" class="btn-primary" style="background: var(--danger); border-color: var(--danger)">Hapus Permanen</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function clientAdmin() {
            return {
                c: null, t: 'grant', edit: { status: 'active', end: '' }, confirmName: '',
                manage(c) { this.c = c; this.t = 'grant'; this.edit = { status: c.status || 'active', end: c.end || '' }; this.confirmName = ''; },
            };
        }
    </script>
</x-app-layout>
