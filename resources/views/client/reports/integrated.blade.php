<x-app-layout>
    @section('title', 'Laporan Keuangan Terpadu')
    @section('subtitle', 'Analisis komprehensif atas posisi dan kinerja keuangan perusahaan')

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <!-- Date Filter Form -->
        <form id="filterForm" action="{{ route('client.reports.integrated') }}" method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Dari:</span>
                <input type="date" name="start_date" value="{{ $startDate }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <div class="flex items-center">
                <span class="text-gray-400 text-sm mr-2">Sampai:</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="bg-gray-800 border-gray-700 text-white focus:ring-trusta-teal rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg text-sm transition-colors">
                Terapkan
            </button>
        </form>

        <div class="flex space-x-3">
            <button type="submit" form="filterForm" name="export" value="excel" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg text-sm transition-colors shadow-lg shadow-emerald-900/50 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export Excel
            </button>
            <button type="button" onclick="window.print()" class="px-4 py-2 bg-red-700 hover:bg-red-600 text-white font-medium rounded-lg text-sm transition-colors shadow-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak / PDF
            </button>
        </div>
    </div>

    <!-- Area Laporan -->
    <div id="printableArea" class="bg-white text-gray-900 shadow-2xl rounded-2xl overflow-hidden max-w-5xl mx-auto">
        
        <style>
            @media print {
                body * { visibility: hidden; }
                #printableArea, #printableArea * { visibility: visible; }
                #printableArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border-radius: 0; }
                .no-print { display: none !important; }
                .page-break { page-break-before: always; break-before: page; }
            }
            .report-table { width: 100%; border-collapse: collapse; }
            .report-table td, .report-table th { padding: 6px 12px; vertical-align: top; }
            .report-table .section-header { font-weight: 700; font-size: 14px; padding-top: 16px; padding-bottom: 8px; border-bottom: 2px solid #1e293b; }
            .report-table .sub-header { font-weight: 600; padding-left: 12px; padding-top: 10px; text-decoration: underline; color: #374151; }
            .report-table .item-row td { padding-left: 36px; border-bottom: 1px solid #f1f5f9; }
            .report-table .subtotal-row td { font-weight: 700; border-top: 1px solid #9ca3af; }
            .report-table .grandtotal-row td { font-weight: 700; font-size: 15px; border-top: 3px double #1e293b; }
            .report-table .amount { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
            .report-table .negative { color: #dc2626; }
        </style>

        <div class="p-10 md:p-14">

            {{-- ============================================ --}}
            {{-- BAGIAN 1: LAPORAN POSISI KEUANGAN (NERACA)   --}}
            {{-- ============================================ --}}
            <div class="text-center mb-10 pb-6" style="border-bottom: 3px double #1e293b;">
                <h2 class="text-2xl font-bold font-poppins uppercase tracking-wider">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</h2>
                @if(auth()->user()->company->address ?? false)
                    <p class="text-xs text-gray-500 mt-1">{{ auth()->user()->company->address }}</p>
                @endif
                <h1 class="text-2xl font-bold font-poppins mt-4 tracking-widest">LAPORAN POSISI KEUANGAN</h1>
                <h3 class="text-lg font-semibold text-gray-500 tracking-wide">(NERACA / BALANCE SHEET)</h3>
                <p class="text-gray-500 font-medium mt-2">
                    Per Tanggal: {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">(Disajikan dalam Rupiah)</p>
            </div>

            <table class="report-table">
                <tbody>
                    <tr><td class="section-header" colspan="2">ASET</td></tr>
                    <tr><td class="sub-header" colspan="2">Aset Lancar</td></tr>
                    @php $totalAsetLancar = 0; @endphp
                    @foreach($assets as $acc)
                        @if(($acc->as_of_balance != 0 || $acc->opening_balance != 0) && str_starts_with($acc->code, '1-1'))
                        <tr class="item-row">
                            <td>{{ $acc->code }} &mdash; {{ $acc->name }}</td>
                            <td class="amount">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
                        </tr>
                        @php $totalAsetLancar += $acc->as_of_balance; @endphp
                        @endif
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Aset Lancar</td>
                        <td class="amount">{{ number_format($totalAsetLancar, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td class="sub-header" colspan="2">Aset Tetap</td></tr>
                    @php $totalAsetTetap = 0; @endphp
                    @foreach($assets as $acc)
                        @if(($acc->as_of_balance != 0 || $acc->opening_balance != 0) && !str_starts_with($acc->code, '1-1'))
                        <tr class="item-row">
                            <td>{{ $acc->code }} &mdash; {{ $acc->name }}</td>
                            <td class="amount">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
                        </tr>
                        @php $totalAsetTetap += $acc->as_of_balance; @endphp
                        @endif
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Aset Tetap</td>
                        <td class="amount">{{ number_format($totalAsetTetap, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>
                    <tr class="grandtotal-row" style="background: #f8fafc;">
                        <td>TOTAL ASET</td>
                        <td class="amount" style="color: #0f766e;">{{ number_format($totalAssets, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 20px;"></td></tr>

                    <tr><td class="section-header" colspan="2">KEWAJIBAN</td></tr>
                    @foreach($liabilities as $acc)
                        @if($acc->as_of_balance != 0 || $acc->opening_balance != 0)
                        <tr class="item-row">
                            <td>{{ $acc->code }} &mdash; {{ $acc->name }}</td>
                            <td class="amount">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
                        </tr>
                        @endif
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Kewajiban</td>
                        <td class="amount">{{ number_format($totalLiabilities, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>

                    <tr><td class="section-header" colspan="2">EKUITAS</td></tr>
                    @foreach($equities as $acc)
                        @if($acc->as_of_balance != 0 || $acc->opening_balance != 0)
                        <tr class="item-row">
                            <td>{{ $acc->code }} &mdash; {{ $acc->name }}</td>
                            <td class="amount">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
                        </tr>
                        @endif
                    @endforeach
                    <tr class="item-row">
                        <td style="font-style: italic;">Laba (Rugi) Ditahan Berjalan</td>
                        <td class="amount {{ $retainedEarnings < 0 ? 'negative' : '' }}">{{ number_format($retainedEarnings, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Ekuitas</td>
                        <td class="amount">{{ number_format($totalEquityAndRetained, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>
                    <tr class="grandtotal-row" style="background: #f8fafc;">
                        <td>TOTAL KEWAJIBAN & EKUITAS</td>
                        <td class="amount" style="color: {{ abs($totalAssets - $totalLiabilitiesAndEquity) > 0.01 ? '#dc2626' : '#0f766e' }};">{{ number_format($totalLiabilitiesAndEquity, 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            @if(abs($totalAssets - $totalLiabilitiesAndEquity) > 0.01)
                <div class="mt-6 bg-red-100 text-red-700 p-4 rounded-lg text-center font-bold no-print">
                    ⚠️ Peringatan: Neraca Tidak Balance! Selisih: Rp {{ number_format(abs($totalAssets - $totalLiabilitiesAndEquity), 2, ',', '.') }}
                </div>
            @else
                <div class="mt-6 bg-emerald-50 text-emerald-700 p-3 rounded-lg text-center text-sm font-medium no-print">
                    ✅ Neraca Balance — Total Aset = Total Kewajiban & Ekuitas
                </div>
            @endif

            {{-- ============================================ --}}
            {{-- PAGE BREAK                                   --}}
            {{-- ============================================ --}}
            <div class="page-break w-full border-t-2 border-dashed border-gray-300 my-16 print:my-0 print:border-none relative">
                <div class="absolute inset-0 flex justify-center -mt-3 no-print">
                    <span class="bg-white px-4 text-sm text-gray-400">— Halaman Baru (Print) —</span>
                </div>
            </div>

            {{-- ============================================ --}}
            {{-- BAGIAN 2: LAPORAN LABA RUGI                  --}}
            {{-- ============================================ --}}
            <div class="text-center mb-10 pb-6" style="border-bottom: 3px double #1e293b;">
                <h2 class="text-2xl font-bold font-poppins uppercase tracking-wider">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</h2>
                @if(auth()->user()->company->address ?? false)
                    <p class="text-xs text-gray-500 mt-1">{{ auth()->user()->company->address }}</p>
                @endif
                <h1 class="text-2xl font-bold font-poppins mt-4 tracking-widest">LAPORAN LABA RUGI</h1>
                <h3 class="text-lg font-semibold text-gray-500 tracking-wide">(INCOME STATEMENT / PROFIT & LOSS)</h3>
                <p class="text-gray-500 font-medium mt-2">
                    Periode: {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">(Disajikan dalam Rupiah)</p>
            </div>

            <table class="report-table">
                <tbody>
                    <tr><td class="section-header" colspan="2">PENDAPATAN USAHA</td></tr>
                    @foreach($revenues as $rev)
                        @if($rev->period_balance != 0)
                        <tr class="item-row">
                            <td>{{ $rev->code }} &mdash; {{ $rev->name }}</td>
                            <td class="amount">{{ number_format($rev->period_balance, 2, ',', '.') }}</td>
                        </tr>
                        @endif
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Pendapatan Usaha</td>
                        <td class="amount">{{ number_format($totalRevenue, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>

                    <tr><td class="section-header" colspan="2">BEBAN USAHA</td></tr>
                    @foreach($expenses as $exp)
                        @if($exp->period_balance != 0)
                        <tr class="item-row">
                            <td>{{ $exp->code }} &mdash; {{ $exp->name }}</td>
                            <td class="amount">({{ number_format($exp->period_balance, 2, ',', '.') }})</td>
                        </tr>
                        @endif
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Beban Usaha</td>
                        <td class="amount negative">({{ number_format($totalExpense, 2, ',', '.') }})</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>

                    <tr class="subtotal-row" style="background: #f8fafc;">
                        <td style="font-size: 14px;">LABA (RUGI) USAHA</td>
                        <td class="amount" style="font-size: 14px; {{ ($totalRevenue - $totalExpense) < 0 ? 'color: #dc2626;' : 'color: #0f766e;' }}">{{ number_format($totalRevenue - $totalExpense, 2, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 8px;"></td></tr>

                    <tr><td class="section-header" colspan="2">PENDAPATAN (BEBAN) LAIN-LAIN</td></tr>
                    <tr class="item-row">
                        <td style="font-style: italic; color: #9ca3af;">Belum ada transaksi lain-lain</td>
                        <td class="amount" style="color: #9ca3af;">0,00</td>
                    </tr>
                    <tr class="subtotal-row">
                        <td style="padding-left: 36px;">Total Pendapatan (Beban) Lain-lain</td>
                        <td class="amount">0,00</td>
                    </tr>

                    <tr><td colspan="2" style="padding: 12px;"></td></tr>

                    <tr class="grandtotal-row" style="background: {{ $netProfit >= 0 ? '#ecfdf5' : '#fef2f2' }};">
                        <td>{{ $netProfit >= 0 ? 'LABA BERSIH (NET PROFIT)' : 'RUGI BERSIH (NET LOSS)' }}</td>
                        <td class="amount" style="font-size: 20px; {{ $netProfit >= 0 ? 'color: #0f766e;' : 'color: #dc2626;' }}">
                            {{ $netProfit >= 0 ? '' : '(' }}Rp {{ number_format(abs($netProfit), 2, ',', '.') }}{{ $netProfit >= 0 ? '' : ')' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Tanda Tangan -->
            <div class="mt-16 flex justify-between items-start px-8">
                <div class="text-center text-sm text-gray-500">
                    <p>Dibuat oleh,</p>
                    <div class="mt-16 border-b border-gray-400 w-48 mx-auto"></div>
                    <p class="mt-1 text-xs">Bagian Keuangan</p>
                </div>
                <div class="text-center text-sm text-gray-500">
                    <p>Mengetahui,</p>
                    <div class="mt-16 border-b border-gray-400 w-48 mx-auto"></div>
                    <p class="mt-1 text-xs">Direktur / Penanggung Jawab</p>
                </div>
            </div>

            <div class="mt-10 text-center text-xs text-gray-400">
                <p>Laporan Keuangan Terpadu dihasilkan secara otomatis oleh sistem akuntansi TRUSTA.</p>
                <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
