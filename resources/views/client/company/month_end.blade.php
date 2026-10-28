<x-app-layout>
@section('title', 'Tutup Buku Akhir Bulan')
@section('subtitle', 'Kunci periode transaksi untuk melindungi integritas data finansial')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- LEFT: Proses Tutup Buku --}}
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white">Kunci Periode Transaksi</h3>
                <p class="text-sm text-gray-400">Mencegah perubahan transaksi di masa lalu</p>
            </div>
        </div>

        {{-- Status saat ini --}}
        <div class="bg-gray-900/60 border border-gray-700 rounded-xl p-4 mb-6">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Tanggal Tutup Buku Saat Ini</p>
            @if($company->closing_date)
                <div class="text-xl font-bold text-white">{{ date('d F Y', strtotime($company->closing_date)) }}</div>
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                    <p class="text-xs text-red-400">Transaksi pada atau sebelum tanggal ini tidak dapat dibuat, diubah, atau dihapus. Pembayaran faktur lama & pembatalan (jurnal balik) tetap bisa dengan tanggal di periode terbuka.</p>
                </div>
            @else
                <div class="text-lg font-semibold text-gray-400">Belum Ada Transaksi Terkunci</div>
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0"></span>
                    <p class="text-xs text-emerald-400">Sistem masih terbuka — semua data dapat diedit.</p>
                </div>
            @endif
        </div>

        <form action="{{ route('client.company.process_month_end') }}" method="POST"
              onsubmit="return confirm('Yakin ingin mengunci transaksi? Transaksi pada atau sebelum tanggal itu tidak bisa dibuat/diubah/dihapus sampai kunci dibuka admin.')">
            @csrf
            <div class="mb-5">
                <label class="block text-sm font-semibold text-gray-300 mb-2">Pilih Tanggal Cut-off</label>
                <input type="date" name="closing_date" required
                    value="{{ date('Y-m-t', strtotime('-1 month')) }}"
                    class="w-full bg-gray-900 border border-gray-700 text-white rounded-xl px-4 py-3 focus:ring-trusta-teal focus:border-trusta-teal">
                <p class="text-xs text-gray-500 mt-2">💡 Rekomendasi: Hari terakhir bulan lalu.</p>
            </div>

            <button type="submit"
                class="w-full py-3 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl transition-all flex justify-center items-center gap-2 shadow-lg shadow-red-900/30">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Kunci Transaksi
            </button>
        </form>
        @if($company->closing_date)
        <form action="{{ route('client.company.process_month_end') }}" method="POST" class="mt-3"
              onsubmit="return confirm('Buka kunci periode? Semua tanggal bisa diubah kembali.')">
            @csrf
            <input type="hidden" name="action" value="unlock">
            <button type="submit" class="w-full py-2.5 border border-gray-600 text-gray-300 hover:text-white hover:border-gray-400 font-semibold rounded-xl transition-all text-sm">Buka Kunci Periode</button>
        </form>
        @endif
    </div>

    {{-- RIGHT: Info cards --}}
    <div class="space-y-5">
        {{-- Card Penyusutan --}}
        <div class="bg-[#1E293B] border border-gray-800 rounded-2xl p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-white mb-1">Penyusutan Aset Tetap</h3>
                    <p class="text-sm text-gray-400 mb-4">Pastikan penyusutan aset tetap sudah dijalankan sebelum menutup periode.</p>
                    <a href="{{ route('client.fixed_assets.index') }}"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-trusta-teal hover:text-teal-300 transition-colors">
                        Ke Modul Aset Tetap
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Card Laba Ditahan --}}
        <div class="bg-[#1E293B] border border-gray-800 rounded-2xl p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-white mb-1">Jurnal Laba Ditahan</h3>
                    <p class="text-sm text-gray-400 mb-4">TRUSTA otomatis menghitung Laba/Rugi Bersih dan mengakumulasikan ke Laba Ditahan — tanpa perlu jurnal penutup manual.</p>
                    <a href="{{ route('client.reports.integrated') }}"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-trusta-teal hover:text-teal-300 transition-colors">
                        Lihat Laporan Neraca
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Card Warning --}}
        <div class="bg-amber-500/5 border border-amber-500/20 rounded-2xl p-5">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-amber-300/80">
                    Hanya admin yang dapat mengunci/membuka periode, dan setiap perubahan tercatat di Log Aktivitas. Pastikan semua data sudah benar sebelum mengunci.
                </p>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
