<x-app-layout>
@section('title', 'Kalkulator Pajak')

<style>
    .calc-container {
        background: var(--surface-2);
        border-radius: 12px;
        min-height: calc(100vh - 120px);
        box-shadow: var(--shadow-pop);
        color: var(--text);
        display: flex;
        flex-direction: column;
    }
    
    .calc-header {
        padding: 30px 40px;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
        position: relative;
        border-radius: 12px 12px 0 0;
    }
    .calc-header-content {
        position: relative;
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .calc-logo {
        width: 60px;
        height: 60px;
        background: var(--surface);
        border: 2px solid #14b8a6;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
    }
    .calc-title h1 {
        font-size: 28px;
        font-weight: 600;
        margin: 0 0 5px 0;
        color: var(--text-strong);
        letter-spacing: -0.5px;
    }
    .calc-title p {
        margin: 0;
        color: var(--text-muted);
        font-size: 15px;
    }
    
    .calc-tabs {
        display: flex;
        padding: 0 40px;
        background: var(--surface);
        border-bottom: 1px solid var(--border-strong);
    }
    .calc-tab {
        padding: 16px 24px;
        font-weight: 600;
        color: var(--text-dim);
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        font-size: 15px;
    }
    .calc-tab:hover {
        color: var(--text);
    }
    .calc-tab.active {
        color: var(--accent);
        border-bottom-color: #14b8a6;
    }
    
    .calc-body {
        padding: 40px;
        flex: 1;
        background: var(--surface-2);
    }
    
    .calc-form-group {
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
    }
    .calc-form-group label {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    .calc-form-group input, .calc-form-group select {
        background: var(--surface);
        border: 1px solid var(--border-strong);
        border-radius: 8px;
        padding: 12px 16px;
        color: var(--text-strong);
        font-size: 15px;
        outline: none;
        transition: all 0.2s;
    }
    .calc-form-group input:focus, .calc-form-group select:focus {
        border-color: #14b8a6;
        box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.2);
    }
    
    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-prefix {
        position: absolute;
        left: 16px;
        color: var(--text-dim);
        font-weight: 500;
    }
    .input-group input {
        padding-left: 45px;
        width: 100%;
    }
    
    .btn-calculate {
        background: #14b8a6;
        color: #0f172a;
        font-weight: 600;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 15px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-calculate:hover {
        background: #0d9488;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
    }
    
    .result-panel {
        background: var(--surface);
        border: 1px solid var(--border-strong);
        border-radius: 12px;
        padding: 30px;
        margin-top: 30px;
        display: none;
    }
    .result-panel.show {
        display: block;
        animation: fadeIn 0.4s ease-out forwards;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .result-title {
        font-size: 18px;
        color: var(--text-strong);
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .result-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-strong);
    }
    .result-row:last-child {
        border-bottom: none;
    }
    .result-label {
        color: var(--text-muted);
    }
    .result-value {
        font-weight: 600;
        color: var(--text-strong);
        font-family: monospace;
        font-size: 16px;
    }
    .result-final {
        font-size: 24px;
        color: #10b981;
    }
    .calc-note {
        margin-top: 14px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--text-dim);
    }
    .calc-sources {
        padding: 18px 40px 26px;
        font-size: 12.5px;
        line-height: 1.6;
        color: var(--text-dim);
        border-top: 1px solid var(--border);
    }
    .result-section {
        margin-top: 16px;
        padding-top: 14px;
        border-top: 2px dashed var(--border-strong);
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
    }
    @media (max-width: 640px) {
        .calc-header, .calc-body, .calc-sources { padding-left: 18px; padding-right: 18px; }
        .calc-tabs { padding: 0 8px; overflow-x: auto; }
        .calc-tab { padding: 14px 12px; white-space: nowrap; }
        .result-panel { padding: 18px; }
        .result-row { flex-wrap: wrap; gap: 2px 12px; }
    }
</style>

<div class="mb-6 flex items-center px-2">
    <a href="{{ route('client.reports.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm font-medium transition-colors">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Kembali ke Daftar Laporan
    </a>
</div>

<div class="calc-container" x-data="taxCalculator()">
    <div class="calc-header">
        <div class="calc-header-content">
            <div class="calc-logo">
                <svg style="width: 32px; height: 32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            </div>
            <div class="calc-title">
                <h1>Kalkulator Pajak</h1>
                <p>Simulasi untuk perkiraan. Hasilnya tidak menggantikan perhitungan resmi di Coretax.</p>
            </div>
        </div>
    </div>
    
    <div class="calc-tabs">
        <div class="calc-tab" :class="{'active': activeTab === 'pph21'}" @click="activeTab = 'pph21'; resetResult()">PPh 21 (Gaji)</div>
        <div class="calc-tab" :class="{'active': activeTab === 'pph23'}" @click="activeTab = 'pph23'; resetResult()">PPh 23 (Jasa)</div>
        <div class="calc-tab" :class="{'active': activeTab === 'ppn'}" @click="activeTab = 'ppn'; resetResult()">PPN</div>
        <div class="calc-tab" :class="{'active': activeTab === 'pph42'}" @click="activeTab = 'pph42'; resetResult()">PPh 4(2) Final</div>
    </div>
    
    <div class="calc-body">
        <!-- PPh 21 Tab -->
        <div x-show="activeTab === 'pph21'">
            <div class="row" style="display: flex; gap: 40px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <div class="calc-form-group">
                        <label>Penghasilan Bruto (Sebulan)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" inputmode="numeric" x-model="pph21.bruto" @input="formatCurrency('pph21', 'bruto')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Status PTKP</label>
                        <select x-model="pph21.ptkp">
                            <option value="TK/0">TK/0: Tidak kawin, 0 tanggungan (Rp 54.000.000)</option>
                            <option value="TK/1">TK/1: Tidak kawin, 1 tanggungan (Rp 58.500.000)</option>
                            <option value="K/0">K/0: Kawin, 0 tanggungan (Rp 58.500.000)</option>
                            <option value="TK/2">TK/2: Tidak kawin, 2 tanggungan (Rp 63.000.000)</option>
                            <option value="K/1">K/1: Kawin, 1 tanggungan (Rp 63.000.000)</option>
                            <option value="TK/3">TK/3: Tidak kawin, 3 tanggungan (Rp 67.500.000)</option>
                            <option value="K/2">K/2: Kawin, 2 tanggungan (Rp 67.500.000)</option>
                            <option value="K/3">K/3: Kawin, 3 tanggungan (Rp 72.000.000)</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Siapa yang Menanggung Pajak</label>
                        <select x-model="pph21.metode">
                            <option value="gross">Karyawan (Gross)</option>
                            <option value="net">Perusahaan, bukan sebagai tunjangan (Net)</option>
                            <option value="grossup">Perusahaan, sebagai tunjangan pajak (Gross Up)</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn-calculate" @click="calculatePph21()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPh 21
                    </button>
                    <p class="calc-note">Mengasumsikan penghasilan sama setiap bulan selama setahun penuh, tanpa bonus dan tanpa iuran pensiun atau BPJS yang menjadi pengurang.</p>
                </div>
                
                <div style="flex: 1; min-width: 280px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'pph21'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Perhitungan PPh 21
                        </div>

                        <div class="result-row" x-show="result.pph21.tunjangan > 0">
                            <span class="result-label">Tunjangan Pajak per Bulan</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.tunjangan)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Kategori dan Tarif TER</span>
                            <span class="result-value" x-text="'TER ' + result.pph21.kategori + ', ' + formatPercent(result.pph21.tarifTer)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PPh 21 Januari sampai November</span>
                            <span class="result-value result-final" x-text="formatToRp(result.pph21.pajakBulanan)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Diterima Karyawan per Bulan</span>
                            <span class="result-value" style="color: #0ea5e9;" x-text="formatToRp(result.pph21.thp)"></span>
                        </div>

                        <div class="result-section">Perhitungan setahun untuk masa Desember (Pasal 17)</div>
                        <div class="result-row">
                            <span class="result-label">Penghasilan Bruto Setahun</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.brutoSetahun)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Biaya Jabatan (Maks 6 Juta per Tahun)</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.biayaJabatan)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PTKP</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.ptkp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Penghasilan Kena Pajak</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.pkp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PPh 21 Setahun</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.pajakSetahun)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label" x-text="result.pph21.pajakDesember >= 0 ? 'PPh 21 Masa Desember' : 'Lebih Potong Masa Desember'"></span>
                            <span class="result-value result-final" x-text="formatToRp(Math.abs(result.pph21.pajakDesember))"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PPh 23 Tab -->
        <div x-show="activeTab === 'pph23'">
            <div class="row" style="display: flex; gap: 40px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div class="calc-form-group">
                        <label>Dasar Pengenaan Pajak (DPP / Nilai Transaksi)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" inputmode="numeric" x-model="pph23.dpp" @input="formatCurrency('pph23', 'dpp')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Jenis Penghasilan (Objek Pajak)</label>
                        <select x-model="pph23.jenis">
                            <option value="jasa">Jasa Teknik, Manajemen, Konsultan, Jasa Lain (2%)</option>
                            <option value="sewa">Sewa dan Penghasilan Lain dari Penggunaan Harta (2%)</option>
                            <option value="modal">Dividen, Bunga, Royalti, Hadiah (15%)</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Status NPWP Lawan Transaksi</label>
                        <select x-model="pph23.npwp">
                            <option value="1">Memiliki NPWP (Tarif Normal)</option>
                            <option value="2">Tidak Memiliki NPWP (Tarif +100%)</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn-calculate" @click="calculatePph23()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPh 23
                    </button>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'pph23'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Pemotongan PPh 23
                        </div>
                        
                        <div class="result-row">
                            <span class="result-label">Nilai Transaksi (DPP)</span>
                            <span class="result-value" x-text="formatToRp(result.pph23.dpp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Tarif Efektif</span>
                            <span class="result-value" x-text="formatPercent(result.pph23.tarif)"></span>
                        </div>
                        <div class="result-row" style="margin-top: 15px; border-top: 2px dashed #475569; padding-top: 15px;">
                            <span class="result-label">Nilai Pajak (PPh 23) dipotong</span>
                            <span class="result-value result-final" x-text="formatToRp(result.pph23.pajak)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Jumlah Bersih Diterima/Dibayar</span>
                            <span class="result-value" style="color: #0ea5e9; font-size: 20px;" x-text="formatToRp(result.pph23.bersih)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PPN Tab -->
        <div x-show="activeTab === 'ppn'">
            <div class="row" style="display: flex; gap: 40px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <div class="calc-form-group">
                        <label>Nilai Transaksi</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" inputmode="numeric" x-model="ppn.nilai" @input="formatCurrency('ppn', 'nilai')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Nilai di Atas</label>
                        <select x-model="ppn.sifat">
                            <option value="exclude">Belum termasuk PPN</option>
                            <option value="include">Sudah termasuk PPN</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Jenis Barang atau Jasa</label>
                        <select x-model="ppn.jenis">
                            <option value="umum">Bukan barang mewah: 12% x DPP nilai lain (11/12), setara 11%</option>
                            <option value="mewah">Barang mewah: 12% x harga jual</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn-calculate" @click="calculatePpn()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPN
                    </button>
                    <p class="calc-note">Berlaku sejak 1 Januari 2025. Barang dan jasa dengan besaran tertentu punya perhitungan sendiri dan tidak tercakup di sini.</p>
                </div>
                
                <div style="flex: 1; min-width: 280px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'ppn'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Perhitungan PPN
                        </div>
                        
                        <div class="result-row">
                            <span class="result-label">Harga Jual / Penggantian</span>
                            <span class="result-value" x-text="formatToRp(result.ppn.harga)"></span>
                        </div>
                        <div class="result-row" x-show="result.ppn.dppLain > 0">
                            <span class="result-label">DPP Nilai Lain (11/12)</span>
                            <span class="result-value" x-text="formatToRp(result.ppn.dppLain)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PPN</span>
                            <span class="result-value result-final" x-text="formatToRp(result.ppn.pajak)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Total Dibayar</span>
                            <span class="result-value" style="color: #0ea5e9; font-size: 20px;" x-text="formatToRp(result.ppn.total)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PPh 4(2) Tab -->
        <div x-show="activeTab === 'pph42'">
            <div class="row" style="display: flex; gap: 40px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div class="calc-form-group">
                        <label>Dasar Pengenaan Pajak (Nilai Transaksi)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" inputmode="numeric" x-model="pph42.dpp" @input="formatCurrency('pph42', 'dpp')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Objek Pajak (Sifat Final)</label>
                        <select x-model="pph42.jenis">
                            <optgroup label="Umum">
                                <option value="sewa">Sewa Tanah dan/atau Bangunan (10%)</option>
                                <option value="umkm">UMKM, Peredaran Bruto Tertentu (0,5%)</option>
                            </optgroup>
                            <optgroup label="Pekerjaan Konstruksi">
                                <option value="kecil">Kualifikasi kecil atau perseorangan bersertifikat (1,75%)</option>
                                <option value="lain">Kualifikasi menengah, besar, atau spesialis (2,65%)</option>
                                <option value="tanpa">Tanpa sertifikat (4%)</option>
                            </optgroup>
                            <optgroup label="Pekerjaan Konstruksi Terintegrasi">
                                <option value="int">Bersertifikat badan usaha (2,65%)</option>
                                <option value="int_tanpa">Tanpa sertifikat (4%)</option>
                            </optgroup>
                            <optgroup label="Konsultansi Konstruksi">
                                <option value="kons">Bersertifikat (3,5%)</option>
                                <option value="kons_tanpa">Tanpa sertifikat (6%)</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <button type="button" class="btn-calculate" @click="calculatePph42()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPh 4(2)
                    </button>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'pph42'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Pemotongan PPh Pasal 4 ayat (2)
                        </div>
                        
                        <div class="result-row">
                            <span class="result-label">Nilai Transaksi (DPP)</span>
                            <span class="result-value" x-text="formatToRp(result.pph42.dpp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Tarif Final</span>
                            <span class="result-value" x-text="formatPercent(result.pph42.tarif)"></span>
                        </div>
                        <div class="result-row" style="margin-top: 15px; border-top: 2px dashed #475569; padding-top: 15px;">
                            <span class="result-label">PPh 4(2) Dipotong</span>
                            <span class="result-value result-final" x-text="formatToRp(result.pph42.pajak)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Sisa Diterima/Dibayar</span>
                            <span class="result-value" style="color: #0ea5e9; font-size: 20px;" x-text="formatToRp(result.pph42.bersih)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="calc-sources">
        Dasar perhitungan: PP 58/2023 dan PMK 168/2023 (TER PPh 21), Pasal 17 UU PPh (tarif tahunan), PMK 131/2024 (PPN), Pasal 23 UU PPh, PP 34/2017 (sewa tanah dan bangunan), PP 55/2022 (UMKM), PP 9/2022 (jasa konstruksi). Periksa aturan terbaru sebelum dipakai untuk pelaporan.
    </div>
</div>

<script>
// Tabel TER bulanan PP 58/2023: [batas atas lapisan, tarif %]. Baris terakhir tanpa batas atas.
const TER_BULANAN = {"A":[[5400000,0.0],[5650000,0.25],[5950000,0.5],[6300000,0.75],[6750000,1.0],[7500000,1.25],[8550000,1.5],[9650000,1.75],[10050000,2.0],[10350000,2.25],[10700000,2.5],[11050000,3.0],[11600000,3.5],[12500000,4.0],[13750000,5.0],[15100000,6.0],[16950000,7.0],[19750000,8.0],[24150000,9.0],[26450000,10.0],[28000000,11.0],[30050000,12.0],[32400000,13.0],[35400000,14.0],[39100000,15.0],[43850000,16.0],[47800000,17.0],[51400000,18.0],[56300000,19.0],[62200000,20.0],[68600000,21.0],[77500000,22.0],[89000000,23.0],[103000000,24.0],[125000000,25.0],[157000000,26.0],[206000000,27.0],[337000000,28.0],[454000000,29.0],[550000000,30.0],[695000000,31.0],[910000000,32.0],[1400000000,33.0],[null,34.0]],"B":[[6200000,0.0],[6500000,0.25],[6850000,0.5],[7300000,0.75],[9200000,1.0],[10750000,1.5],[11250000,2.0],[11600000,2.5],[12600000,3.0],[13600000,4.0],[14950000,5.0],[16400000,6.0],[18450000,7.0],[21850000,8.0],[26000000,9.0],[27700000,10.0],[29350000,11.0],[31450000,12.0],[33950000,13.0],[37100000,14.0],[41100000,15.0],[45800000,16.0],[49500000,17.0],[53800000,18.0],[58500000,19.0],[64000000,20.0],[71000000,21.0],[80000000,22.0],[93000000,23.0],[109000000,24.0],[129000000,25.0],[163000000,26.0],[211000000,27.0],[374000000,28.0],[459000000,29.0],[555000000,30.0],[704000000,31.0],[957000000,32.0],[1405000000,33.0],[null,34.0]],"C":[[6600000,0.0],[6950000,0.25],[7350000,0.5],[7800000,0.75],[8850000,1.0],[9800000,1.25],[10950000,1.5],[11200000,1.75],[12050000,2.0],[12950000,3.0],[14150000,4.0],[15550000,5.0],[17050000,6.0],[19500000,7.0],[22700000,8.0],[26600000,9.0],[28100000,10.0],[30100000,11.0],[32600000,12.0],[35400000,13.0],[38900000,14.0],[43000000,15.0],[47400000,16.0],[51200000,17.0],[55800000,18.0],[60400000,19.0],[66700000,20.0],[74500000,21.0],[83200000,22.0],[95600000,23.0],[110000000,24.0],[134000000,25.0],[169000000,26.0],[221000000,27.0],[390000000,28.0],[463000000,29.0],[561000000,30.0],[709000000,31.0],[965000000,32.0],[1419000000,33.0],[null,34.0]]};
const PTKP = { 'TK/0': [54000000, 'A'], 'TK/1': [58500000, 'A'], 'K/0': [58500000, 'A'], 'TK/2': [63000000, 'B'], 'K/1': [63000000, 'B'], 'TK/3': [67500000, 'B'], 'K/2': [67500000, 'B'], 'K/3': [72000000, 'C'] };
const TARIF_PPH23 = { jasa: 2, sewa: 2, modal: 15 };
const TARIF_PPH42 = { sewa: 10, umkm: 0.5, kecil: 1.75, lain: 2.65, tanpa: 4, int: 2.65, int_tanpa: 4, kons: 3.5, kons_tanpa: 6 };

function tarifTer(kategori, bruto) {
    return TER_BULANAN[kategori].find((r) => r[0] === null || bruto <= r[0])[1];
}

/** PPh 21 setahun dengan tarif Pasal 17, dari penghasilan bruto setahun. */
function pph21Setahun(brutoSetahun, ptkp) {
    const biayaJabatan = Math.min(brutoSetahun * 0.05, 6000000);
    const pkp = Math.max(0, Math.floor((brutoSetahun - biayaJabatan - ptkp) / 1000) * 1000);
    let sisa = pkp, pajak = 0;
    for (const [lebar, tarif] of [[60000000, 0.05], [190000000, 0.15], [250000000, 0.25], [4500000000, 0.30], [Infinity, 0.35]]) {
        const kena = Math.min(sisa, lebar);
        pajak += kena * tarif;
        sisa -= kena;
    }
    return { biayaJabatan, pkp, pajak };
}

/** Gross Up TER: tunjangan sebesar pajaknya sendiri, jadi dicari lapisan tempat bruto setelah tunjangan benar-benar jatuh. */
function tunjanganTer(kategori, bruto) {
    let bawah = 0;
    for (const [atas, tarif] of TER_BULANAN[kategori]) {
        const total = Math.max(bruto / (1 - tarif / 100), bawah);
        if (atas === null || total <= atas) return total - bruto;
        bawah = atas + 1;
    }
    return 0;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('taxCalculator', () => ({
        activeTab: 'pph21',
        hasResult: false,
        
        pph21: { bruto: '', ptkp: 'TK/0', metode: 'gross' },
        pph23: { dpp: '', jenis: 'jasa', npwp: '1' },
        ppn: { nilai: '', sifat: 'exclude', jenis: 'umum' },
        pph42: { dpp: '', jenis: 'sewa' },
        
        result: {
            pph21: { kategori: 'A', tarifTer: 0, tunjangan: 0, pajakBulanan: 0, thp: 0, brutoSetahun: 0, biayaJabatan: 0, ptkp: 0, pkp: 0, pajakSetahun: 0, pajakDesember: 0 },
            pph23: { dpp: 0, tarif: 0, pajak: 0, bersih: 0 },
            ppn: { harga: 0, dppLain: 0, pajak: 0, total: 0 },
            pph42: { dpp: 0, tarif: 0, pajak: 0, bersih: 0 }
        },
        
        // Titik pemisah ribuan, koma desimal. Yang di belakang koma tidak boleh tergabung ke angka ribuannya.
        parseNumber(val) {
            if(!val) return 0;
            const [bulat, desimal] = val.toString().split(',');
            return parseFloat(bulat.replace(/[^0-9]/g, '') + (desimal ? '.' + desimal.replace(/[^0-9]/g, '') : '')) || 0;
        },
        
        formatCurrency(obj, field) {
            const [bulat, ...desimal] = this[obj][field].toString().split(',');
            const angka = bulat.replace(/[^0-9]/g, '');
            if(!angka) {
                this[obj][field] = '';
                return;
            }
            const teks = parseInt(angka, 10).toLocaleString('id-ID');
            this[obj][field] = desimal.length ? teks + ',' + desimal.join('').replace(/[^0-9]/g, '').slice(0, 2) : teks;
        },
        
        formatToRp(val) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
        },

        formatPercent(val) {
            return val.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%';
        },
        
        resetResult() {
            this.hasResult = false;
        },
        
        calculatePph21() {
            const bruto = this.parseNumber(this.pph21.bruto);
            if(bruto <= 0) return;

            const [ptkp, kategori] = PTKP[this.pph21.ptkp];
            let tunjangan = 0, brutoBulanan = bruto, brutoSetahun = bruto * 12, pajakBulanan, setahun, pajakDesember;

            if (this.pph21.metode === 'grossup') {
                tunjangan = tunjanganTer(kategori, bruto);
                brutoBulanan = bruto + tunjangan;
                pajakBulanan = tunjangan;
                // Masa Desember: tunjangan bulan itu ikut menjadi penghasilan setahun, jadi dihitung berulang sampai stabil
                pajakDesember = 0;
                for (let i = 0; i < 100; i++) {
                    brutoSetahun = 12 * bruto + 11 * tunjangan + Math.max(pajakDesember, 0);
                    setahun = pph21Setahun(brutoSetahun, ptkp);
                    pajakDesember = setahun.pajak - 11 * tunjangan;
                }
            } else {
                pajakBulanan = bruto * tarifTer(kategori, bruto) / 100;
                setahun = pph21Setahun(brutoSetahun, ptkp);
                pajakDesember = setahun.pajak - 11 * pajakBulanan;
            }

            this.result.pph21 = {
                kategori, tarifTer: tarifTer(kategori, brutoBulanan), tunjangan, pajakBulanan,
                thp: this.pph21.metode === 'gross' ? bruto - pajakBulanan : bruto,
                brutoSetahun, biayaJabatan: setahun.biayaJabatan, ptkp, pkp: setahun.pkp, pajakSetahun: setahun.pajak, pajakDesember,
            };
            this.hasResult = true;
        },
        
        calculatePph23() {
            const dpp = this.parseNumber(this.pph23.dpp);
            if(dpp <= 0) return;
            
            let tarifDasar = TARIF_PPH23[this.pph23.jenis];
            if(this.pph23.npwp === '2') {
                tarifDasar *= 2; // +100% jika tidak punya NPWP
            }
            
            const pajak = dpp * (tarifDasar / 100);
            
            this.result.pph23 = { dpp, tarif: tarifDasar, pajak, bersih: dpp - pajak };
            this.hasResult = true;
        },
        
        calculatePpn() {
            const nilai = this.parseNumber(this.ppn.nilai);
            if(nilai <= 0) return;

            // Bukan barang mewah: 12% x (11/12 x harga) setara 11% dari harga
            const umum = this.ppn.jenis === 'umum';
            const tarif = umum ? 11 : 12;
            const harga = this.ppn.sifat === 'include' ? nilai * 100 / (100 + tarif) : nilai;
            const pajak = harga * tarif / 100;

            this.result.ppn = { harga, dppLain: umum ? harga * 11 / 12 : 0, pajak, total: harga + pajak };
            this.hasResult = true;
        },
        
        calculatePph42() {
            const dpp = this.parseNumber(this.pph42.dpp);
            if(dpp <= 0) return;
            
            const tarifDasar = TARIF_PPH42[this.pph42.jenis];
            const pajak = dpp * (tarifDasar / 100);
            
            this.result.pph42 = { dpp, tarif: tarifDasar, pajak, bersih: dpp - pajak };
            this.hasResult = true;
        }
    }));
});
</script>
</x-app-layout>
