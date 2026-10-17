<x-app-layout>
    @section('title', 'Daftar Laporan')
    @section('subtitle', 'Pilih laporan untuk dilihat, diunduh ke Excel, atau dicetak ke PDF')

    @php
        $i = [
            'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'trend' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
            'scale' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
            'book' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
            'list' => 'M4 6h16M4 10h16M4 14h16M4 18h16',
            'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            'doc' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'box' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'calc' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        ];
        $groups = [
            ['Keuangan', [
                ['client.reports.integrated', 'Laporan Keuangan Terpadu', 'Laba rugi dan neraca dalam satu dokumen.', 'chart', true],
                ['client.reports.profit-loss', 'Laba Rugi', 'Pendapatan, beban, dan laba/rugi bersih per periode.', 'trend', true],
                ['client.reports.balance-sheet', 'Neraca', 'Posisi aset, kewajiban, dan ekuitas per tanggal.', 'scale', true],
            ]],
            ['Buku Besar', [
                ['client.reports.general-ledger', 'Buku Besar', 'Mutasi dan saldo berjalan tiap akun.', 'book', true],
                ['client.reports.trial-balance', 'Neraca Saldo', 'Saldo debit dan kredit seluruh akun.', 'list', true],
            ]],
            ['Piutang & Penjualan', [
                ['client.reports.receivable-aging', 'Umur Piutang', 'Piutang per faktur menurut umur jatuh tempo.', 'clock', true],
                ['client.reports.receivable-summary', 'Rekap Piutang per Pelanggan', 'Total tagihan, terbayar, dan sisa tiap pelanggan.', 'users', true],
                ['client.reports.sales-report', 'Daftar Faktur Penjualan', 'Seluruh faktur penjualan pada periode.', 'doc', true],
            ]],
            ['Hutang & Pembelian', [
                ['client.reports.payable-aging', 'Umur Hutang', 'Hutang per faktur menurut umur jatuh tempo.', 'clock', true],
                ['client.reports.payable-summary', 'Rekap Hutang per Pemasok', 'Total tagihan, terbayar, dan sisa tiap pemasok.', 'users', true],
                ['client.reports.purchase-report', 'Daftar Faktur Pembelian', 'Seluruh faktur pembelian pada periode.', 'doc', true],
            ]],
            ['Persediaan', [
                ['client.reports.inventory-stock', 'Stok Persediaan', 'Stok, harga beli, dan nilai persediaan per barang.', 'box', true],
            ]],
            ['Pajak', [
                ['client.reports.spt_ppn', 'SPT PPN / PPNBM', 'Ringkasan PPN keluaran dan masukan.', 'doc', false],
                ['client.reports.tax_calculator', 'Kalkulator Pajak', 'Hitung PPN, PPh 21, 22, 23, dan lainnya.', 'calc', false],
            ]],
        ];
    @endphp

    <div class="rl-search">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" id="rlSearch" placeholder="Cari laporan..." autocomplete="off">
    </div>

    @foreach($groups as [$title, $items])
        <section class="rl-group">
            <div class="rl-group-title">{{ $title }}</div>
            <div class="rl-grid">
                @foreach($items as [$route, $name, $desc, $icon, $exportable])
                    <a href="{{ route($route) }}" class="rl-card" data-name="{{ strtolower($name . ' ' . $desc) }}">
                        <div class="rl-ico"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $i[$icon] }}"/></svg></div>
                        <div>
                            <h3>{{ $name }}</h3>
                            <p>{{ $desc }}</p>
                            @if($exportable)<div class="rl-tags"><span class="rl-tag">Excel</span><span class="rl-tag">PDF</span><span class="rl-tag">CSV</span></div>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <script>
        document.getElementById('rlSearch').addEventListener('input', function (e) {
            var q = e.target.value.trim().toLowerCase();
            document.querySelectorAll('.rl-card').forEach(function (c) { c.style.display = !q || c.dataset.name.indexOf(q) > -1 ? '' : 'none'; });
            document.querySelectorAll('.rl-group').forEach(function (g) {
                g.style.display = [].some.call(g.querySelectorAll('.rl-card'), function (c) { return c.style.display !== 'none'; }) ? '' : 'none';
            });
        });
    </script>
</x-app-layout>
