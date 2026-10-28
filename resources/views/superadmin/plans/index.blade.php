<x-app-layout>
    @section('title', 'Paket Langganan')
    @section('subtitle', 'Atur nama, harga bulanan & tahunan, batas pengguna, dan fitur tiap paket')

    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $blank = ['name' => '', 'description' => '', 'price' => '', 'price_yearly' => '', 'trial_days' => 0, 'max_users' => 3, 'features' => '', 'sort_order' => 0,
                  'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => false, 'is_active' => true, 'is_public' => true];
    @endphp

    <div x-data="{ open: false, id: null, f: @js($blank), blank: @js($blank),
                  add() { this.id = null; this.f = { ...this.blank }; this.open = true; },
                  edit(id, d) { this.id = id; this.f = { ...d }; this.open = true; } }">
        <div class="rpt-actions" style="margin-bottom: 14px; justify-content: space-between;">
            <p class="pay-flow" style="margin:0">Harga baru berlaku untuk pembayaran berikutnya; langganan yang sedang berjalan tidak berubah. Paket "tidak ditampilkan" hanya bisa diberikan manual oleh admin.</p>
            <button type="button" class="rpt-btn primary" @click="add()">+ Paket Baru</button>
        </div>

        <div class="pay-card" x-show="open" x-cloak>
            <div class="pay-card-head"><h3 x-text="id ? 'Ubah Paket' : 'Paket Baru'"></h3></div>
            <form method="POST" :action="id ? '{{ url('superadmin/plans') }}/' + id : '{{ route('superadmin.plans.store') }}'">
                @csrf
                <template x-if="id"><input type="hidden" name="_method" value="PUT"></template>
                <div class="pay-form-grid">
                    <div class="pay-field"><label>Nama Paket *</label><input type="text" name="name" x-model="f.name" required maxlength="100" placeholder="mis. Bisnis"></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Deskripsi Singkat</label><input type="text" name="description" x-model="f.description" maxlength="255" placeholder="mis. Untuk usaha kecil 1–3 orang"></div>
                    <div class="pay-field"><label>Harga / Bulan (Rp) *</label><input type="text" name="price" class="money-in" x-model="f.price" required></div>
                    <div class="pay-field"><label>Harga / Tahun (Rp)</label><input type="text" name="price_yearly" class="money-in" x-model="f.price_yearly" placeholder="kosong = 12 × bulanan"></div>
                    <div class="pay-field"><label>Maks. Pengguna *</label><input type="number" name="max_users" min="1" x-model="f.max_users" required></div>
                    <div class="pay-field"><label>Urutan Tampil</label><input type="number" name="sort_order" x-model="f.sort_order"></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Keunggulan Tambahan (satu per baris)</label><textarea name="features" rows="3" x-model="f.features" placeholder="Dukungan prioritas&#10;Pelatihan online"></textarea></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Fitur & Status</label>
                        <div style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:6px; font-size:.82rem; color: var(--text); padding-top:4px">
                            @foreach(['has_reports' => 'Laporan keuangan', 'has_cash_bank' => 'Kas & bank', 'has_export' => 'Ekspor Excel/PDF', 'has_auto_backup' => 'Backup otomatis', 'is_active' => 'Aktif', 'is_public' => 'Ditampilkan ke klien'] as $k => $l)
                                <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="{{ $k }}" value="1" x-model="f.{{ $k }}"> {{ $l }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="pay-foot"><span></span><span><button type="button" class="rpt-btn" @click="open = false">Batal</button> <button type="submit" class="btn-primary" x-text="id ? 'Simpan' : 'Buat Paket'"></button></span></div>
            </form>
        </div>

        <div class="plan-grid" style="padding: 0; margin-bottom: 18px;">
            @forelse($plans as $p)
                <div class="plan-card" style="cursor: default; {{ $p->is_active ? '' : 'opacity:.6' }}">
                    <div class="plan-name">{{ $p->name }}
                        @if(!$p->is_active)<span class="st st-void" style="text-decoration:none">Nonaktif</span>@elseif(!$p->is_public)<span class="st st-partial">Tersembunyi</span>@endif
                    </div>
                    @if($p->description)<div class="plan-desc">{{ $p->description }}</div>@endif
                    <div class="plan-price">{{ $rp($p->price) }}<small>/bulan</small></div>
                    <div class="plan-desc">{{ $rp($p->yearlyPrice()) }}/tahun · {{ $p->active_count }} klien aktif</div>
                    <ul class="plan-feats">
                        <li>Hingga {{ $p->max_users }} pengguna</li>
                        @if($p->has_reports)<li>Laporan keuangan</li>@endif
                        @if($p->has_cash_bank)<li>Kas & bank</li>@endif
                        @if($p->has_export)<li>Ekspor Excel/PDF</li>@endif
                        @if($p->has_auto_backup)<li>Backup otomatis</li>@endif
                        @foreach($p->featureList() as $ft)<li>{{ $ft }}</li>@endforeach
                    </ul>
                    <div style="display:flex; gap:6px; margin-top:12px">
                        <button type="button" class="pay-mini" @click="edit({{ $p->id }}, @js(['name' => $p->name, 'description' => $p->description, 'price' => number_format((float) $p->price, 2, ',', '.'), 'price_yearly' => $p->price_yearly ? number_format((float) $p->price_yearly, 2, ',', '.') : '', 'trial_days' => $p->trial_days, 'max_users' => $p->max_users, 'features' => $p->features, 'sort_order' => $p->sort_order, 'has_reports' => (bool) $p->has_reports, 'has_cash_bank' => (bool) $p->has_cash_bank, 'has_export' => (bool) $p->has_export, 'has_auto_backup' => (bool) $p->has_auto_backup, 'is_active' => (bool) $p->is_active, 'is_public' => (bool) $p->is_public]))">Ubah</button>
                        <form method="POST" action="{{ route('superadmin.plans.destroy', $p) }}" onsubmit="return confirm('Hapus paket {{ addslashes($p->name) }}? Bila sudah pernah dipakai, paket hanya dinonaktifkan.')">@csrf @method('DELETE')<button type="submit" class="pay-mini dng">Hapus</button></form>
                    </div>
                </div>
            @empty
                <div class="pay-card" style="grid-column: 1 / -1"><div class="pay-empty">Belum ada paket. Klik "+ Paket Baru".</div></div>
            @endforelse
        </div>
    </div>
</x-app-layout>
