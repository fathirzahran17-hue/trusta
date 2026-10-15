<x-app-layout>
@section('title', 'Kalkulator Pajak')

<style>
    .calc-container {
        background: #0f172a;
        border-radius: 12px;
        min-height: calc(100vh - 120px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        color: #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    
    .calc-header {
        padding: 30px 40px;
        border-bottom: 1px solid #1e293b;
        background: url('https://static.pajak.go.id/assets/media/bg/background-kalkulator.jpg') center/cover no-repeat;
        position: relative;
        border-radius: 12px 12px 0 0;
    }
    .calc-header::before {
        content: "";
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.85); /* Overlay */
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
        background: #1e293b;
        border: 2px solid #14b8a6;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #14b8a6;
    }
    .calc-title h1 {
        font-size: 28px;
        font-weight: 600;
        margin: 0 0 5px 0;
        color: #f8fafc;
        letter-spacing: -0.5px;
    }
    .calc-title p {
        margin: 0;
        color: #94a3b8;
        font-size: 15px;
    }
    
    .calc-tabs {
        display: flex;
        padding: 0 40px;
        background: #1e293b;
        border-bottom: 1px solid #334155;
    }
    .calc-tab {
        padding: 16px 24px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        font-size: 15px;
    }
    .calc-tab:hover {
        color: #cbd5e1;
    }
    .calc-tab.active {
        color: #14b8a6;
        border-bottom-color: #14b8a6;
    }
    
    .calc-body {
        padding: 40px;
        flex: 1;
        background: #0f172a;
    }
    
    .calc-form-group {
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
    }
    .calc-form-group label {
        font-size: 14px;
        font-weight: 500;
        color: #94a3b8;
        margin-bottom: 8px;
    }
    .calc-form-group input, .calc-form-group select {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 8px;
        padding: 12px 16px;
        color: #f8fafc;
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
        color: #64748b;
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
        background: #1e293b;
        border: 1px solid #334155;
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
        color: #f8fafc;
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
        border-bottom: 1px solid #334155;
    }
    .result-row:last-child {
        border-bottom: none;
    }
    .result-label {
        color: #94a3b8;
    }
    .result-value {
        font-weight: 600;
        color: #f8fafc;
        font-family: monospace;
        font-size: 16px;
    }
    .result-final {
        font-size: 24px;
        color: #10b981;
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
                <p>Simulasi penghitungan pajak sesuai dengan standar Direktorat Jenderal Pajak (DJP)</p>
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
                <div style="flex: 1; min-width: 300px;">
                    <div class="calc-form-group">
                        <label>Penghasilan Bruto (Sebulan)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" x-model="pph21.bruto" @input="formatCurrency('pph21', 'bruto')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Status PTKP</label>
                        <select x-model="pph21.ptkp">
                            <option value="54000000">TK/0 - Tidak Kawin, 0 Tanggungan (Rp 54.000.000)</option>
                            <option value="58500000">TK/1 atau K/0 - Kawin/Tidak Kawin, 1 Tanggungan (Rp 58.500.000)</option>
                            <option value="63000000">TK/2 atau K/1 - Kawin/Tidak Kawin, 2 Tanggungan (Rp 63.000.000)</option>
                            <option value="67500000">TK/3 atau K/2 - Kawin/Tidak Kawin, 3 Tanggungan (Rp 67.500.000)</option>
                            <option value="72000000">K/3 - Kawin, 3 Tanggungan (Rp 72.000.000)</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Metode Pemotongan</label>
                        <select x-model="pph21.metode">
                            <option value="gross">Nett / Gross (Pajak ditanggung karyawan)</option>
                            <option value="grossup">Gross Up (Tunjangan pajak dari perusahaan)</option>
                        </select>
                    </div>
                    
                    <button class="btn-calculate" @click="calculatePph21()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPh 21
                    </button>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'pph21'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Perhitungan PPh 21
                        </div>
                        
                        <div class="result-row">
                            <span class="result-label">Penghasilan Bruto Setahun</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.brutoSetahun)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Biaya Jabatan (Maks 6Jt/thn)</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.biayaJabatan)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Penghasilan Neto Setahun</span>
                            <span class="result-value" x-text="formatToRp(result.pph21.netoSetahun)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PTKP</span>
                            <span class="result-value text-red-400" x-text="'- ' + formatToRp(result.pph21.ptkp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">PKP (Penghasilan Kena Pajak)</span>
                            <span class="result-value text-amber-400" x-text="formatToRp(result.pph21.pkp)"></span>
                        </div>
                        <div class="result-row" style="margin-top: 15px; border-top: 2px dashed #475569; padding-top: 15px;">
                            <span class="result-label">PPh 21 Sebulan</span>
                            <span class="result-value result-final" x-text="formatToRp(result.pph21.pajakSebulan)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Take Home Pay (THP)</span>
                            <span class="result-value" style="color: #0ea5e9; font-size: 20px;" x-text="formatToRp(result.pph21.thp)"></span>
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
                            <input type="text" x-model="pph23.dpp" @input="formatCurrency('pph23', 'dpp')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Jenis Penghasilan (Objek Pajak)</label>
                        <select x-model="pph23.jenis">
                            <option value="2">Jasa Teknik, Manajemen, Konsultan, Jasa Lain (2%)</option>
                            <option value="2_sewa">Sewa & Penghasilan Lain dari Penggunaan Harta (2%)</option>
                            <option value="15">Dividen, Bunga, Royalti, Hadiah (15%)</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Status NPWP Lawan Transaksi</label>
                        <select x-model="pph23.npwp">
                            <option value="1">Memiliki NPWP (Tarif Normal)</option>
                            <option value="2">Tidak Memiliki NPWP (Tarif +100%)</option>
                        </select>
                    </div>
                    
                    <button class="btn-calculate" @click="calculatePph23()">
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
                            <span class="result-value" x-text="result.pph23.tarif + '%'"></span>
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
                <div style="flex: 1; min-width: 300px;">
                    <div class="calc-form-group">
                        <label>Nilai Transaksi</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="text" x-model="ppn.nilai" @input="formatCurrency('ppn', 'nilai')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Sifat Perhitungan</label>
                        <select x-model="ppn.sifat">
                            <option value="exclude">Belum Termasuk PPN (Exclude)</option>
                            <option value="include">Sudah Termasuk PPN (Include)</option>
                        </select>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Tarif PPN</label>
                        <select x-model="ppn.tarif">
                            <option value="11">11% (Berlaku saat ini)</option>
                            <option value="12">12% (Masa depan UU HPP)</option>
                        </select>
                    </div>
                    
                    <button class="btn-calculate" @click="calculatePpn()">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Hitung PPN
                    </button>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <div class="result-panel" :class="{'show': hasResult && activeTab === 'ppn'}">
                        <div class="result-title">
                            <svg style="width: 24px; height: 24px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Hasil Perhitungan PPN
                        </div>
                        
                        <div class="result-row">
                            <span class="result-label">Dasar Pengenaan Pajak (DPP)</span>
                            <span class="result-value" x-text="formatToRp(result.ppn.dpp)"></span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">Nilai PPN</span>
                            <span class="result-value text-amber-400" x-text="'+ ' + formatToRp(result.ppn.pajak)"></span>
                        </div>
                        <div class="result-row" style="margin-top: 15px; border-top: 2px dashed #475569; padding-top: 15px;">
                            <span class="result-label">Total Dibayar (DPP + PPN)</span>
                            <span class="result-value result-final" x-text="formatToRp(result.ppn.total)"></span>
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
                            <input type="text" x-model="pph42.dpp" @input="formatCurrency('pph42', 'dpp')" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="calc-form-group">
                        <label>Objek Pajak (Sifat Final)</label>
                        <select x-model="pph42.jenis">
                            <option value="10">Sewa Tanah dan/atau Bangunan (10%)</option>
                            <option value="1">Jasa Konstruksi - Pelaksana Kualifikasi Kecil (1.75% / 1%)</option>
                            <option value="0.5">UMKM (Peredaran Bruto Tertentu PP 23) (0.5%)</option>
                        </select>
                    </div>
                    
                    <button class="btn-calculate" @click="calculatePph42()">
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
                            <span class="result-value" x-text="result.pph42.tarif + '%'"></span>
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
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('taxCalculator', () => ({
        activeTab: 'pph21',
        hasResult: false,
        
        pph21: { bruto: '', ptkp: '54000000', metode: 'gross' },
        pph23: { dpp: '', jenis: '2', npwp: '1' },
        ppn: { nilai: '', sifat: 'exclude', tarif: '11' },
        pph42: { dpp: '', jenis: '10' },
        
        result: {
            pph21: { brutoSetahun: 0, biayaJabatan: 0, netoSetahun: 0, ptkp: 0, pkp: 0, pajakSetahun: 0, pajakSebulan: 0, thp: 0 },
            pph23: { dpp: 0, tarif: 0, pajak: 0, bersih: 0 },
            ppn: { dpp: 0, pajak: 0, total: 0 },
            pph42: { dpp: 0, tarif: 0, pajak: 0, bersih: 0 }
        },
        
        parseNumber(val) {
            if(!val) return 0;
            return parseFloat(val.toString().replace(/[^0-9]/g, '')) || 0;
        },
        
        formatCurrency(obj, field) {
            let val = this.parseNumber(this[obj][field]);
            if(val === 0) {
                this[obj][field] = '';
                return;
            }
            this[obj][field] = val.toLocaleString('id-ID');
        },
        
        formatToRp(val) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
        },
        
        resetResult() {
            this.hasResult = false;
        },
        
        calculatePph21() {
            const brutoSebulan = this.parseNumber(this.pph21.bruto);
            if(brutoSebulan <= 0) return;
            
            const brutoSetahun = brutoSebulan * 12;
            let biayaJabatan = brutoSetahun * 0.05;
            if (biayaJabatan > 6000000) biayaJabatan = 6000000;
            
            const netoSetahun = brutoSetahun - biayaJabatan;
            const ptkp = parseInt(this.pph21.ptkp);
            
            let pkp = Math.floor((netoSetahun - ptkp) / 1000) * 1000;
            if (pkp < 0) pkp = 0;
            
            // Tarif Progresif
            let pajakSetahun = 0;
            let sisaPkp = pkp;
            
            if (sisaPkp > 0) {
                const tier1 = Math.min(sisaPkp, 60000000);
                pajakSetahun += tier1 * 0.05;
                sisaPkp -= tier1;
            }
            if (sisaPkp > 0) {
                const tier2 = Math.min(sisaPkp, 190000000); // 250jt - 60jt
                pajakSetahun += tier2 * 0.15;
                sisaPkp -= tier2;
            }
            if (sisaPkp > 0) {
                const tier3 = Math.min(sisaPkp, 250000000); // 500jt - 250jt
                pajakSetahun += tier3 * 0.25;
                sisaPkp -= tier3;
            }
            if (sisaPkp > 0) {
                const tier4 = Math.min(sisaPkp, 4500000000); // 5M - 500jt
                pajakSetahun += tier4 * 0.30;
                sisaPkp -= tier4;
            }
            if (sisaPkp > 0) {
                pajakSetahun += sisaPkp * 0.35; // Di atas 5M
            }
            
            let pajakSebulan = pajakSetahun / 12;
            let thp = brutoSebulan;
            
            if(this.pph21.metode === 'gross') {
                thp = brutoSebulan - pajakSebulan;
            } else {
                // Gross up kasar
                thp = brutoSebulan;
            }
            
            this.result.pph21 = { brutoSetahun, biayaJabatan, netoSetahun, ptkp, pkp, pajakSetahun, pajakSebulan, thp };
            this.hasResult = true;
        },
        
        calculatePph23() {
            const dpp = this.parseNumber(this.pph23.dpp);
            if(dpp <= 0) return;
            
            let tarifDasar = parseFloat(this.pph23.jenis);
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
            
            const tarifRate = parseInt(this.ppn.tarif);
            let dpp, pajak, total;
            
            if(this.ppn.sifat === 'include') {
                dpp = nilai * (100 / (100 + tarifRate));
                pajak = nilai - dpp;
                total = nilai;
            } else {
                dpp = nilai;
                pajak = dpp * (tarifRate / 100);
                total = dpp + pajak;
            }
            
            this.result.ppn = { dpp, pajak, total };
            this.hasResult = true;
        },
        
        calculatePph42() {
            const dpp = this.parseNumber(this.pph42.dpp);
            if(dpp <= 0) return;
            
            const tarifDasar = parseFloat(this.pph42.jenis);
            const pajak = dpp * (tarifDasar / 100);
            
            this.result.pph42 = { dpp, tarif: tarifDasar, pajak, bersih: dpp - pajak };
            this.hasResult = true;
        }
    }));
});
</script>
</x-app-layout>
