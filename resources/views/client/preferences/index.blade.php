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
        align-items: center;
        margin-bottom: 16px;
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

<div class="pref-container" x-data="{ activeMenu: 'perusahaan', activeTab: 'info' }">
    <!-- Left Sidebar -->
    <div class="pref-sidebar">
        <div class="pref-sidebar-item" :class="activeMenu === 'perusahaan' ? 'active' : ''" @click="activeMenu = 'perusahaan'">Perusahaan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'fitur' ? 'active' : ''" @click="activeMenu = 'fitur'">Fitur</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pajak' ? 'active' : ''" @click="activeMenu = 'pajak'">Pajak</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'penjualan' ? 'active' : ''" @click="activeMenu = 'penjualan'">Penjualan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pembelian' ? 'active' : ''" @click="activeMenu = 'pembelian'">Pembelian</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'pembatasan' ? 'active' : ''" @click="activeMenu = 'pembatasan'">Pembatasan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'persetujuan' ? 'active' : ''" @click="activeMenu = 'persetujuan'">Persetujuan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'lampiran' ? 'active' : ''" @click="activeMenu = 'lampiran'">Lampiran</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'atribut' ? 'active' : ''" @click="activeMenu = 'atribut'">Atribut Tambahan</div>
        <div class="pref-sidebar-item" :class="activeMenu === 'akun' ? 'active' : ''" @click="activeMenu = 'akun'">Akun Perkiraan</div>
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
                </div>

                <!-- Form Area -->
                <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area" x-show="activeTab === 'info'">
                    @csrf
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
                        <div class="pref-label">Tgl Mulai Data / Tahun Fiskal</div>
                        <input type="date" name="fiscal_year_start" class="pref-input" value="{{ old('fiscal_year_start', $company->fiscal_year_start) }}" style="max-width: 200px;" />
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Periode Akuntansi</div>
                        <select class="pref-input" style="max-width: 250px;">
                            <option>Januari - Desember</option>
                        </select>
                    </div>

                    <div class="pref-form-group">
                        <div class="pref-label">Mata Uang</div>
                        <input type="text" class="pref-input" value="Indonesian Rupiah" disabled />
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Info</button>
                    </div>
                </form>

                <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area" x-show="activeTab === 'alamat'" style="display: none;">
                    @csrf
                    <div class="pref-form-group" style="align-items: flex-start;">
                        <div class="pref-label">Alamat Lengkap</div>
                        <textarea name="address" class="pref-input" rows="4" placeholder="Jalan, Kota, Provinsi, Kode Pos...">{{ old('address', $company->address) }}</textarea>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Alamat</button>
                    </div>
                </form>
            </div>
        </template>
        
        <template x-if="activeMenu === 'fitur'">
            <div class="pref-form-area">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Fitur Dasar</h3>
                
                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" checked disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Pencatatan Multi Mata Uang</span>
                    </label>
                </div>
                
                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" checked disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Manajemen Persediaan & Multi Gudang</span>
                    </label>
                </div>

                <div class="pref-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                        <input type="checkbox" checked disabled style="margin-right: 12px; width: 16px; height: 16px;" />
                        <span>Perhitungan Pajak Pertambahan Nilai (PPN)</span>
                    </label>
                </div>
                
                <p style="margin-top: 30px; font-size: 0.85rem; color: var(--trusta-text-muted);">* Fitur dikendalikan oleh paket langganan Anda.</p>
            </div>
        </template>
        
        <template x-if="activeMenu === 'akun'">
            <div class="pref-form-area">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Akun Perkiraan Default</h3>
                
                <div class="pref-form-group">
                    <div class="pref-label">Akun Hutang</div>
                    <select class="pref-input">
                        <option>2110.01 - Hutang Usaha IDR</option>
                    </select>
                </div>
                
                <div class="pref-form-group">
                    <div class="pref-label">Akun Piutang</div>
                    <select class="pref-input">
                        <option>1130.01 - Piutang Usaha IDR</option>
                    </select>
                </div>
                
                <div class="pref-form-group">
                    <div class="pref-label">Akun Selisih Kurs</div>
                    <select class="pref-input">
                        <option>7110.01 - Laba/Rugi Selisih Kurs Belum Terealisasi</option>
                    </select>
                </div>
                
                <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                    <button class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pemetaan</button>
                </div>
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
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Sistem Pembulatan Pajak</div>
                        <select name="tax_rounding" class="pref-input">
                            <option value="Normal (Matematis)" {{ old('tax_rounding', $company->tax_rounding) == 'Normal (Matematis)' ? 'selected' : '' }}>Normal (Matematis)</option>
                            <option value="Pembulatan Ke Atas" {{ old('tax_rounding', $company->tax_rounding) == 'Pembulatan Ke Atas' ? 'selected' : '' }}>Pembulatan Ke Atas</option>
                            <option value="Pembulatan Ke Bawah" {{ old('tax_rounding', $company->tax_rounding) == 'Pembulatan Ke Bawah' ? 'selected' : '' }}>Pembulatan Ke Bawah</option>
                        </select>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan Pajak</button>
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
                            <option value="Net 30" {{ old('default_sales_term', $company->default_sales_term) == 'Net 30' ? 'selected' : '' }}>Net 30</option>
                            <option value="Cash On Delivery" {{ old('default_sales_term', $company->default_sales_term) == 'Cash On Delivery' ? 'selected' : '' }}>Cash On Delivery</option>
                        </select>
                    </div>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="checkbox" name="warn_below_cost" value="1" {{ old('warn_below_cost', $company->warn_below_cost) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Beri peringatan jika harga jual di bawah Harga Pokok</span>
                        </label>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
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
                            <option value="Net 30" {{ old('default_purchase_term', $company->default_purchase_term) == 'Net 30' ? 'selected' : '' }}>Net 30</option>
                            <option value="Net 60" {{ old('default_purchase_term', $company->default_purchase_term) == 'Net 60' ? 'selected' : '' }}>Net 60</option>
                        </select>
                    </div>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="checkbox" name="prevent_over_receive" value="1" {{ old('prevent_over_receive', $company->prevent_over_receive) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Cegah penerimaan barang jika melebihi kuantitas pesanan</span>
                        </label>
                    </div>
                    
                    <div class="pref-form-group" style="margin-top: 20px;">
                        <div class="pref-label">Metode Penilaian Persediaan (HPP)</div>
                        <select name="inventory_costing_method" class="pref-input">
                            <option value="average" {{ old('inventory_costing_method', $company->inventory_costing_method ?? 'average') == 'average' ? 'selected' : '' }}>Rata-rata Bergerak (Moving Average)</option>
                            <option value="fifo" {{ old('inventory_costing_method', $company->inventory_costing_method ?? 'average') == 'fifo' ? 'selected' : '' }}>Masuk Pertama Keluar Pertama (FIFO)</option>
                        </select>
                        <p style="font-size: 0.8rem; color: #9ca3af; margin-top: 5px;">*Perubahan metode ini akan mempengaruhi perhitungan HPP di setiap faktur penjualan ke depan.</p>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
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
                            <input type="checkbox" name="prevent_prior_transactions" value="1" {{ old('prevent_prior_transactions', $company->prevent_prior_transactions) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Cegah transaksi sebelum Tanggal Mulai Data</span>
                        </label>
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label" style="width: 250px;">Batas Akhir Kunci Periode Transaksi</div>
                        <input type="date" name="closing_date" value="{{ old('closing_date', $company->closing_date) }}" class="pref-input" style="max-width: 150px;" />
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>

        <template x-if="activeMenu === 'persetujuan'">
            <form action="{{ route('client.settings.preferences.update') }}" method="POST" class="pref-form-area">
                @csrf
                <div style="flex: 1;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--trusta-teal); margin-bottom: 20px;">Persetujuan Transaksi (Approval)</h3>
                    <p style="margin-bottom: 16px; font-size: 0.9rem; color: var(--trusta-text-muted);">Gunakan menu khusus "Penyetuju Transaksi" untuk mengatur struktur hierarki approval secara detail.</p>
                    <div class="pref-form-group">
                        <label style="display: flex; align-items: center; cursor: pointer; color: var(--trusta-text-primary);">
                            <input type="checkbox" name="notify_approver" value="1" {{ old('notify_approver', $company->notify_approver) ? 'checked' : '' }} style="margin-right: 12px; width: 16px; height: 16px;" />
                            <span>Kirim notifikasi email kepada Penyetuju saat ada transaksi baru</span>
                        </label>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
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
                        <input type="number" name="max_attachment_size" class="pref-input" value="{{ old('max_attachment_size', $company->max_attachment_size) }}" style="max-width: 100px;" />
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Format Diizinkan (koma dipisah)</div>
                        <input type="text" name="allowed_attachment_types" class="pref-input" value="{{ old('allowed_attachment_types', $company->allowed_attachment_types) }}" />
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
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
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Atribut Khusus Barang 1</div>
                        <input type="text" name="custom_item_attr_1" class="pref-input" value="{{ old('custom_item_attr_1', $company->custom_item_attr_1) }}" placeholder="Misal: Nomor Rak" />
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
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
                    </div>
                    <div class="pref-form-group">
                        <div class="pref-label">Bahasa Standar</div>
                        <select name="default_language" class="pref-input" style="max-width: 150px;">
                            <option value="Bahasa Indonesia" {{ old('default_language', $company->default_language) == 'Bahasa Indonesia' ? 'selected' : '' }}>Bahasa Indonesia</option>
                            <option value="English" {{ old('default_language', $company->default_language) == 'English' ? 'selected' : '' }}>English</option>
                        </select>
                    </div>
                    <div style="margin-top: 30px; display: flex; justify-content: flex-end; max-width: 580px;">
                        <button type="submit" class="btn-primary" style="background: var(--trusta-teal); color: var(--trusta-darker); padding: 8px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </template>
    </div>
</div>
</x-app-layout>
