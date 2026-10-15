<table>
    {{-- ============================= --}}
    {{-- HALAMAN 1: LAPORAN NERACA     --}}
    {{-- ============================= --}}
    <thead>
        <tr>
            <th colspan="3" style="font-size:16pt; font-weight:bold; text-align:center;">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</th>
        </tr>
        <tr>
            <th colspan="3" style="font-size:13pt; font-weight:bold; text-align:center;">LAPORAN POSISI KEUANGAN (NERACA)</th>
        </tr>
        <tr>
            <th colspan="3" style="font-size:10pt; text-align:center;">Per Tanggal: {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</th>
        </tr>
        <tr>
            <th colspan="3" style="font-size:9pt; text-align:center; color:#666;">(Disajikan dalam Rupiah)</th>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <th style="font-weight:bold; border-bottom:2px solid #000; width:50px;">No.</th>
            <th style="font-weight:bold; border-bottom:2px solid #000; width:350px;">Keterangan</th>
            <th style="font-weight:bold; border-bottom:2px solid #000; text-align:right; width:200px;">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        {{-- ASET --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">ASET</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td style="font-weight:bold; text-decoration:underline;">Aset Lancar</td>
            <td></td>
        </tr>
        @php $no = 1; $totalAsetLancar = 0; @endphp
        @foreach($assets as $acc)
            @if(($acc->as_of_balance != 0 || $acc->opening_balance != 0) && str_starts_with($acc->code, '1-1'))
            <tr>
                <td style="text-align:center;">{{ $no++ }}</td>
                <td style="padding-left:20px;">{{ $acc->code }} - {{ $acc->name }}</td>
                <td style="text-align:right;">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
            </tr>
            @php $totalAsetLancar += $acc->as_of_balance; @endphp
            @endif
        @endforeach
        <tr>
            <td></td>
            <td style="font-weight:bold; padding-left:20px;">Total Aset Lancar</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">{{ number_format($totalAsetLancar, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td></td>
            <td style="font-weight:bold; text-decoration:underline;">Aset Tetap</td>
            <td></td>
        </tr>
        @php $totalAsetTetap = 0; @endphp
        @foreach($assets as $acc)
            @if(($acc->as_of_balance != 0 || $acc->opening_balance != 0) && !str_starts_with($acc->code, '1-1'))
            <tr>
                <td style="text-align:center;">{{ $no++ }}</td>
                <td style="padding-left:20px;">{{ $acc->code }} - {{ $acc->name }}</td>
                <td style="text-align:right;">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
            </tr>
            @php $totalAsetTetap += $acc->as_of_balance; @endphp
            @endif
        @endforeach
        <tr>
            <td></td>
            <td style="font-weight:bold; padding-left:20px;">Total Aset Tetap</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">{{ number_format($totalAsetTetap, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">TOTAL ASET</td>
            <td style="text-align:right; font-weight:bold; font-size:11pt; border-top:2px solid #000; border-bottom:3px double #000;">{{ number_format($totalAssets, 2, ',', '.') }}</td>
        </tr>

        <tr><td colspan="3"></td></tr>

        {{-- KEWAJIBAN --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">KEWAJIBAN</td>
            <td></td>
        </tr>
        @php $noL = 1; @endphp
        @foreach($liabilities as $acc)
            @if($acc->as_of_balance != 0 || $acc->opening_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $noL++ }}</td>
                <td style="padding-left:20px;">{{ $acc->code }} - {{ $acc->name }}</td>
                <td style="text-align:right;">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
            </tr>
            @endif
        @endforeach
        <tr>
            <td></td>
            <td style="font-weight:bold; padding-left:20px;">Total Kewajiban</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">{{ number_format($totalLiabilities, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>

        {{-- EKUITAS --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">EKUITAS</td>
            <td></td>
        </tr>
        @php $noE = 1; @endphp
        @foreach($equities as $acc)
            @if($acc->as_of_balance != 0 || $acc->opening_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $noE++ }}</td>
                <td style="padding-left:20px;">{{ $acc->code }} - {{ $acc->name }}</td>
                <td style="text-align:right;">{{ number_format($acc->as_of_balance, 2, ',', '.') }}</td>
            </tr>
            @endif
        @endforeach
        <tr>
            <td style="text-align:center;">{{ $noE++ }}</td>
            <td style="padding-left:20px; font-style:italic;">Laba (Rugi) Ditahan Berjalan</td>
            <td style="text-align:right;">{{ number_format($retainedEarnings, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="font-weight:bold; padding-left:20px;">Total Ekuitas</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">{{ number_format($totalEquityAndRetained, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">TOTAL KEWAJIBAN & EKUITAS</td>
            <td style="text-align:right; font-weight:bold; font-size:11pt; border-top:2px solid #000; border-bottom:3px double #000;">{{ number_format($totalLiabilitiesAndEquity, 2, ',', '.') }}</td>
        </tr>

        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>

        {{-- ================================ --}}
        {{-- HALAMAN 2: LAPORAN LABA RUGI     --}}
        {{-- ================================ --}}
        <tr>
            <td colspan="3" style="font-size:16pt; font-weight:bold; text-align:center;">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</td>
        </tr>
        <tr>
            <td colspan="3" style="font-size:13pt; font-weight:bold; text-align:center;">LAPORAN LABA RUGI</td>
        </tr>
        <tr>
            <td colspan="3" style="font-size:10pt; text-align:center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s.d. {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</td>
        </tr>
        <tr>
            <td colspan="3" style="font-size:9pt; text-align:center; color:#666;">(Disajikan dalam Rupiah)</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td style="font-weight:bold; border-bottom:2px solid #000;">No.</td>
            <td style="font-weight:bold; border-bottom:2px solid #000;">Keterangan</td>
            <td style="font-weight:bold; border-bottom:2px solid #000; text-align:right;">Jumlah (Rp)</td>
        </tr>

        {{-- PENDAPATAN --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">PENDAPATAN USAHA</td>
            <td></td>
        </tr>
        @php $noR = 1; @endphp
        @foreach($revenues as $rev)
            @if($rev->period_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $noR++ }}</td>
                <td style="padding-left:20px;">{{ $rev->code }} - {{ $rev->name }}</td>
                <td style="text-align:right;">{{ number_format($rev->period_balance, 2, ',', '.') }}</td>
            </tr>
            @endif
        @endforeach
        <tr>
            <td></td>
            <td style="font-weight:bold;">Total Pendapatan Usaha</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">{{ number_format($totalRevenue, 2, ',', '.') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>

        {{-- BEBAN --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">BEBAN USAHA</td>
            <td></td>
        </tr>
        @php $noB = 1; @endphp
        @foreach($expenses as $exp)
            @if($exp->period_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $noB++ }}</td>
                <td style="padding-left:20px;">{{ $exp->code }} - {{ $exp->name }}</td>
                <td style="text-align:right;">({{ number_format($exp->period_balance, 2, ',', '.') }})</td>
            </tr>
            @endif
        @endforeach
        <tr>
            <td></td>
            <td style="font-weight:bold;">Total Beban Usaha</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">({{ number_format($totalExpense, 2, ',', '.') }})</td>
        </tr>
        <tr><td colspan="3"></td></tr>

        {{-- LABA BERSIH --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:12pt;">{{ $netProfit >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td>
            <td style="text-align:right; font-weight:bold; font-size:12pt; border-top:2px solid #000; border-bottom:3px double #000;">{{ number_format($netProfit, 2, ',', '.') }}</td>
        </tr>

        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td colspan="3" style="font-size:8pt; color:#999; text-align:center;">Laporan Keuangan Terpadu dihasilkan oleh sistem akuntansi TRUSTA - Dicetak pada: {{ now()->format('d M Y H:i') }}</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td></td>
            <td></td>
            <td style="text-align:center; font-size:9pt;">Mengetahui,</td>
        </tr>
        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>
        <tr>
            <td></td>
            <td></td>
            <td style="text-align:center; font-size:9pt; border-bottom:1px solid #000;">(__________________)</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td style="text-align:center; font-size:8pt;">Direktur / Penanggung Jawab</td>
        </tr>
    </tbody>
</table>
