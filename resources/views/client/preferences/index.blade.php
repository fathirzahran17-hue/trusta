<x-app-layout>

@section('title', 'Preferensi')
@section('subtitle', 'Pengaturan Master Preferensi')


<style>
    .pref-container {
        display: flex;
        gap: 20px;
        height: 100%;
        min-height: 600px;
    }
    
    .pref-sidebar {
        width: 220px;
        flex-shrink: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
    }
    .dark .pref-sidebar {
        background: var(--trusta-card);
        border-color: var(--trusta-border);
    }
    
    .pref-sidebar-item {
        padding: 12px 16px;
        display: block;
        color: #4b5563;
        font-weight: 500;
        font-size: 0.9rem;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: all 0.2s;
    }
    .dark .pref-sidebar-item {
        color: #9ca3af;
        border-bottom-color: var(--trusta-border);
    }
    .pref-sidebar-item:hover {
        background: #f9fafb;
    }
    .dark .pref-sidebar-item:hover {
        background: rgba(255,255,255,0.02);
    }
    .pref-sidebar-item.active {
        background: #fff;
        color: var(--trusta-primary, #f43f5e);
        border-left: 3px solid var(--trusta-primary, #f43f5e);
        font-weight: 600;
    }
    .dark .pref-sidebar-item.active {
        background: var(--trusta-dark);
        color: var(--trusta-teal);
        border-left-color: var(--trusta-teal);
    }
    
    .pref-content {
        flex: 1;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 0;
        overflow: hidden;
    }
    .dark .pref-content {
        background: var(--trusta-card);
        border-color: var(--trusta-border);
    }
    
    .pref-inner-tabs {
        display: flex;
        border-bottom: 1px solid #e5e7eb;
        background: #f8f9fa;
    }
    .dark .pref-inner-tabs {
        border-bottom-color: var(--trusta-border);
        background: var(--trusta-darker);
    }
    .pref-inner-tab {
        padding: 12px 20px;
        font-size: 0.9rem;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        border-top: 2px solid transparent;
        background: #e5e7eb;
        margin-right: 2px;
        border-radius: 6px 6px 0 0;
    }
    .dark .pref-inner-tab {
        color: #9ca3af;
        background: var(--trusta-dark);
    }
    .pref-inner-tab.active {
        background: #fff;
        color: #1f2937;
        border-top-color: var(--trusta-primary, #f43f5e);
    }
    .dark .pref-inner-tab.active {
        background: var(--trusta-card);
        color: #f3f4f6;
        border-top-color: var(--trusta-teal);
    }
    
    .pref-form-area {
        padding: 24px;
    }
    
    .pref-form-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 16px;
    }
    .pref-hint {
        flex-basis: 100%;
        max-width: 580px;
        margin: 6px 0 0 180px;
        font-size: 0.78rem;
        line-height: 1.45;
        color: var(--text-dim);
    }
    @media (max-width: 640px) {
        .pref-hint { margin-left: 0; }
    }
    
    .pref-label {
        width: 180px;
        flex-shrink: 0;
        font-size: 0.85rem;
        font-weight: 500;
        color: #374151;
    }
    .dark .pref-label {
        color: #d1d5db;
    }
    
    .pref-input {
        flex: 1;
        max-width: 400px;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.9rem;
        background: #fff;
        color: #1f2937;
    }
    .dark .pref-input {
        background: var(--trusta-dark);
        border-color: var(--trusta-border);
        color: #f3f4f6;
    }
    .pref-input:focus {
        outline: none;
        border-color: var(--trusta-teal);
        box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.1);
    }
    .pref-input:disabled {
        background: #f3f4f6;
        color: #9ca3af;
    }
    .dark .pref-input:disabled {
        background: #1f2937;
        color: #6b7280;
    }
    
    /* Simulate tags for the select inputs in screenshot */
    .pref-tag-input {
        display: inline-flex;
        align-items: center;
        background: #e0e7ff;
        color: #4338ca;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        border: 1px solid #c7d2fe;
    }
    .dark .pref-tag-input {
        background: rgba(20, 184, 166, 0.1);
        color: var(--trusta-teal);
        border-color: rgba(20, 184, 166, 0.2);
    }
    .pref-tag-input .close-btn {
        margin-left: 6px;
        cursor: pointer;
        font-weight: bold;
    }
</style>

<div class="pref-container" x-data="{ activeMenu: 'perusahaan', activeTab: '{{ request('tab') === 'bidang' ? 'bidang' : 'info' }}' }">
    <!-- Left Sidebar -->
    <div class="pref-sidebar">
        <div class="pref-sidebar-item" :class="activeMenu === 'perusahaan' ? 'active' : ''" @click="activeMenu = 'perusahaan'">Perusahaan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'fitur' ? 'active' : ''" @click="activeMenu = 'fitur'">Fitur</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pajak' ? 'active' : ''" @click="activeMenu = 'pajak'">Pajak</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'penjualan' ? 'active' : ''" @click="activeMenu = 'penjualan'">Penjualan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pembelian' ? 'active' : ''" @click="activeMenu = 'pembelian'">Pembelian</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pembatasan' ? 'active' : ''" @click="activeMenu = 'pembatasan'">Pembatasan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'lampiran' ? 'active' : ''" @click="activeMenu = 'lampiran'">Lampiran</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'atribut' ? 'active' : ''" @click="activeMenu = 'atribut'">Atribut Tambahan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'lainnya' ? 'active' : ''" @click="activeMenu = 'lainnya'">Lain-lain</div>
    </div>

    <!-- Main Content -->
    <div class="pref-content">
        <template x-if="activeMenu === 'perusahaan'">
            <div>
                <!-- Inner Tabs -->
                <div class="pref-inner-tabs">
                    <div class="pref-inner-tab" :class="activeTab === 'info' ? 'active' : ''" @click="activeTab = 'info'">Info Perusahaan</div>
                    <div class="pref-inner-tab" :class="activeTab === 'alamat' ? 'active' : ''" @click="activeTab = 'alamat'">Alamat</div>
                    <div class="pref-inner-tab" :class="activeTab === 'bidang' ? 'active' : ''" @click="activeTab = 'bidang'">Bidang Usaha</div>
                </div>

                <!-- Form Area -->
                <form action="{{ route('client.settings.preferences.update') }}" method="POST" enctype="multipart/form-data" class="pref-form-area" x-show="activeTab === 'info'">
                    @csrf
                    <div class="pref-form-group">
                        <div class="pref-label">Logo Perusahaan</div>
                        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                            @if($company->logo_url)<img src="{{ $company->logo_url }}" alt="Logo" class="pref-logo-preview">@endif
                            <div>
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="pref-input" style="max-width: 320px;" />
                                <div style="font-size:.7rem; color: var(--text-dim); margin-top:4px;">PNG/JPG/WEBP, maks 1 MB. Tampil di header dan kop laporan.</div>
                            </div>
                        </div>
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Nama <span class="text-red-500">*</span></div>
                        <input type="text" name="name" class="pref-input" value="{{ old('name', $company->name) }}" required />
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">NPWP</div>
                        <input type="text" name="npwp" class="pref-input" value="{{ old('npwp', $company->npwp) }}" placeholder="00.000.000.0-000.000" />
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Telepon</div>
                        <input type="text" name="phone" class="pref-input" value="{{ old('phone', $company->phone) }}" />
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Email</div>
                        <input type="email" name="email" class="pref-input" value="{{ old('email', $company->email) }}" />
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Awal Tahun Fiskal</div>
                        <select name="fiscal_year_start" class="pref-input" style="max-width: 250px;">
                            @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $bulan)
                                <option value="{{ $i + 1 }}" @selected((int) old('fiscal_year_start', $company->fiscal_year_start ?: 1) === $i + 1)>{{ $bulan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Mata Uang</div>
                        <input type="text" class="pref-input" value="Indonesian Rupiah" disabled />
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Info</button>
                    </div>
                </form>

                <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area" x-show="activeTab === 'alamat'" style="display: none;">
                    @csrf
                    <div class="pref-form-group" style="align-items: flex-start;">
                        <div class="pref-label">Alamat Lengkap</div>
                        <textarea name="address" class="pref-input" rows="4" placeholder="Jalan, Kota, Provinsi, Kode Pos...">{{ old('address', $company->address) }}</textarea>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Alamat</button>
                    </div>
                </form>

                <form action="{{ route('client.settings.business_type') }}" method="POST" class="pref-form-area" x-show="activeTab === 'bidang'" style="display: none;"
                      onsubmit="return confirm('Ubah bidang usaha? Menu menyesuaikan dan akun standar bidang ini yang belum ada akan ditambahkan. Akun & transaksi yang sudah ada tidak diubah.')">
                    @csrf
                    @php $curBt = \App\Support\BusinessType::normalize($company->business_type); @endphp
                    <p style="font-size:.82rem; color: var(--text-muted); max-width: 640px; margin: 0 0 14px;">
                        Bidang usaha menentukan menu yang tampil (mis. Jasa tidak memakai menu persediaan, Manufaktur mendapat menu Produksi)
                        dan akun standar yang disiapkan. Saat ini: <b style="color: var(--text-strong)">{{ \App\Support\BusinessType::label($curBt) }}</b>.
                    </p>
                    <div class="auth-biz" style="max-width: 640px;">
                        @foreach(\App\Support\BusinessType::TYPES as $key => $bt)
                            <label class="auth-biz-opt">
                                <input type="radio" name="business_type" value="{{ $key }}" @checked($curBt === $key)>
                                <span><b>{{ $bt['label'] }}</b><small>{{ $bt['desc'] }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; max-width: 640px;">
                        <button type="submit" class="btn-primary">Simpan Bidang Usaha</button>
                    </div>
                </form>
            </div>
        </template>
        
        <template x-if="activeMenu === 'fitur'">
            <div class="pref-form-area">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Fitur Dasar</h3>
                
                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" @checked(\App\Support\BusinessType::hasStock($company->business_type)) disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Persediaan &amp; Multi Gudang</span>
                    </label>
                </div>

                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" @checked(\App\Support\BusinessType::hasProduction($company->business_type)) disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Produksi (Resep dan Perintah Produksi)</span>
                    </label>
                </div>

                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" checked disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Pajak Pertambahan Nilai (PPN) dan Laporan PPN</span>
                    </label>
                </div>
                
                <p style="margin-top: 30px; font-size: 0.85rem; color: var(--trusta-text-muted);">* Persediaan dan Produksi mengikuti Jenis Usaha, yang bisa diubah di menu Perusahaan.</p>
            </div>
        </template>
        
        <template x-if="activeMenu === 'pajak'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Pengaturan Pajak</h3>
                    <div class="pref-form-group">
                        <div class="pref-label">Tarif PPN Default (%)</div>
                        <input type="number" name="default_tax_rate" class="pref-input" value="{{ old('default_tax_rate', $company->default_tax_rate) }}" style="max-width: 100px;" step="0.01" />
                    <div class="pref-hint">Pajak dengan tarif ini langsung terpilih di dokumen baru. Isi 0 agar dokumen baru dimulai tanpa pajak.</div>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Sistem Pembulatan Pajak</div>
                        <select name="tax_rounding" class="pref-input">
                            <option value="Normal (Matematis)" {{ old('tax_rounding', $company->tax_rounding) == 'Normal (Matematis)' ? 'selected' : '' }}>Normal (Matematis)</option>
                            <option value="Pembulatan Ke Atas" {{ old('tax_rounding', $company->tax_rounding) == 'Pembulatan Ke Atas' ? 'selected' : '' }}>Pembulatan Ke Atas</option>
                            <option value="Pembulatan Ke Bawah" {{ old('tax_rounding', $company->tax_rounding) == 'Pembulatan Ke Bawah' ? 'selected' : '' }}>Pembulatan Ke Bawah</option>
                        </select>
                    <div class="pref-hint">Normal menyimpan dua desimal. Ke Atas dan Ke Bawah membulatkan PPN ke rupiah penuh. Berlaku untuk dokumen baru.</div>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan Pajak</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'penjualan'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Preferensi Penjualan</h3>
                    <div class="pref-form-group">
                        <div class="pref-label">Syarat Pembayaran Default</div>
                        <select name="default_sales_term" class="pref-input">
                            @foreach(\App\Support\PaymentTerms::options((int) $company->id) as $termName => $termDays)
                                <option value="{{ $termName }}" @selected(old('default_sales_term', $company->default_sales_term) === $termName)>{{ $termName }}</option>
                            @endforeach
                        </select>
                        <div class="pref-hint">Mengisi Jatuh Tempo otomatis di faktur baru. Jangka waktu pada data pelanggan atau pemasok lebih diutamakan.</div>
                    </div>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="hidden" name="warn_below_cost" value="0" />
                            <input type="checkbox" name="warn_below_cost" value="1" {{ old('warn_below_cost', $company->warn_below_cost) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Beri peringatan jika harga jual di bawah Harga Pokok</span>
                        </label>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'pembelian'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Preferensi Pembelian</h3>
                    <div class="pref-form-group">
                        <div class="pref-label">Syarat Pembayaran Default</div>
                        <select name="default_purchase_term" class="pref-input">
                            @foreach(\App\Support\PaymentTerms::options((int) $company->id) as $termName => $termDays)
                                <option value="{{ $termName }}" @selected(old('default_purchase_term', $company->default_purchase_term) === $termName)>{{ $termName }}</option>
                            @endforeach
                        </select>
                        <div class="pref-hint">Mengisi Jatuh Tempo otomatis di faktur baru. Jangka waktu pada data pelanggan atau pemasok lebih diutamakan.</div>
                    </div>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="hidden" name="prevent_over_receive" value="0" />
                            <input type="checkbox" name="prevent_over_receive" value="1" {{ old('prevent_over_receive', $company->prevent_over_receive) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Cegah penerimaan barang jika melebihi kuantitas pesanan</span>
                        </label>
                    </div>
                    
                    <div class="pref-form-group" style="margin-top: 20px;">
                        <div class="pref-label">Metode Penilaian Persediaan (HPP)</div>
                        <select name="inventory_costing_method" class="pref-input" @disabled($costingLocked)>
                            <option value="average" {{ old('inventory_costing_method', $company->inventory_costing_method ?? 'average') == 'average' ? 'selected' : '' }}>Rata-rata Bergerak (Moving Average)</option>
                            <option value="fifo" {{ old('inventory_costing_method', $company->inventory_costing_method ?? 'average') == 'fifo' ? 'selected' : '' }}>Masuk Pertama Keluar Pertama (FIFO)</option>
                        </select>
                        @if($costingLocked)
                            <p class="pref-hint">Terkunci karena sudah ada mutasi stok. Nilai persediaan dan HPP yang tercatat memakai metode ini.</p>
                        @else
                            <p class="pref-hint">Tentukan sebelum transaksi pertama. Setelah ada mutasi stok, metode ini tidak bisa diganti.</p>
                        @endif
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'pembatasan'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Pembatasan & Keamanan</h3>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="hidden" name="prevent_prior_transactions" value="0" />
                            <input type="checkbox" name="prevent_prior_transactions" value="1" {{ old('prevent_prior_transactions', $company->prevent_prior_transactions) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Cegah transaksi sebelum Tanggal Mulai Data</span>
                        </label>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Tanggal Mulai Data</div>
                        <input type="date" name="data_start_date" value="{{ old('data_start_date', $company->data_start_date ? \Illuminate\Support\Carbon::parse($company->data_start_date)->format('Y-m-d') : '') }}" class="pref-input" style="max-width: 180px;" />
                        <div class="pref-hint">Transaksi bertanggal sebelum ini ditolak selama pembatasan di atas dicentang. Kosongkan bila tidak perlu.</div>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label" style="width: 250px;">Kunci Periode Transaksi</div>
                        <div style="flex: 1; min-width: 220px; font-size: 0.875rem; color: var(--text); line-height: 1.5;">
                            {{ $company->closing_date ? 'Terkunci sampai ' . \App\Support\ReportFormat::date($company->closing_date) . '.' : 'Belum ada periode yang dikunci.' }}
                            <a href="{{ route('client.company.month_end') }}" style="color: var(--accent); font-weight: 600;">Atur di Tutup Buku Bulanan</a>
                        </div>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'lampiran'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Lampiran Berkas</h3>
                    <div class="pref-form-group">
                        <div class="pref-label">Batas Ukuran File (MB)</div>
                        <input type="number" name="max_attachment_size" class="pref-input" min="1" max="10" value="{{ old('max_attachment_size', $company->max_attachment_size) }}" style="max-width: 100px;" />
                        <div class="pref-hint">Maksimal 10 MB. Lampiran bisa ditambahkan di Faktur, Pesanan, dan Jurnal.</div>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Format Diizinkan (koma dipisah)</div>
                        <input type="text" name="allowed_attachment_types" class="pref-input" value="{{ old('allowed_attachment_types', $company->allowed_attachment_types) }}" />
                        <div class="pref-hint">Pisahkan dengan koma. Pilihan: PDF, JPG, PNG, WEBP, XLSX, XLS, DOCX, DOC, CSV, TXT.</div>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'atribut'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Atribut Tambahan</h3>
                    <p style="margin-bottom: 16px; font-size: 0.9rem; color: var(--trusta-text-muted);">Ubah nama atribut khusus untuk Modul Pelanggan dan Barang.</p>
                    <div class="pref-form-group">
                        <div class="pref-label">Atribut Khusus Pelanggan 1</div>
                        <input type="text" name="custom_customer_attr_1" class="pref-input" value="{{ old('custom_customer_attr_1', $company->custom_customer_attr_1) }}" placeholder="Misal: Nomor Member" />
                        <div class="pref-hint">Menambah satu kolom di form pelanggan. Kosongkan bila tidak perlu.</div>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Atribut Khusus Barang 1</div>
                        <input type="text" name="custom_item_attr_1" class="pref-input" value="{{ old('custom_item_attr_1', $company->custom_item_attr_1) }}" placeholder="Misal: Nomor Rak" />
                        <div class="pref-hint">Menambah satu kolom di form barang dan jasa. Kosongkan bila tidak perlu.</div>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'lainnya'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Preferensi Lain-lain</h3>
                    <div class="pref-form-group">
                        <div class="pref-label">Format Desimal Harga</div>
                        <select name="price_decimal_format" class="pref-input" style="max-width: 150px;">
                            <option value="2 Angka (0.00)" {{ old('price_decimal_format', $company->price_decimal_format) == '2 Angka (0.00)' ? 'selected' : '' }}>2 Angka (0.00)</option>
                            <option value="Tanpa Desimal" {{ old('price_decimal_format', $company->price_decimal_format) == 'Tanpa Desimal' ? 'selected' : '' }}>Tanpa Desimal</option>
                        </select>
                        <div class="pref-hint">Hanya mengubah tampilan. Nilai tersimpan dan jurnal tetap dua desimal.</div>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Bahasa Standar</div>
                        <select name="default_language" class="pref-input" style="max-width: 150px;">
                            <option value="Bahasa Indonesia" {{ old('default_language', $company->default_language) == 'Bahasa Indonesia' ? 'selected' : '' }}>Bahasa Indonesia</option>
                            <option value="English" {{ old('default_language', $company->default_language) == 'English' ? 'selected' : '' }}>English</option>
                        </select>
                        <div class="pref-hint">Dipakai pengguna yang belum memilih bahasanya sendiri di menu atas.</div>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>
    </div>
</div>
</x-app-layout>
