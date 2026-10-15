<table>
    <thead>
        <tr>
            <th colspan="3" style="font-size:16pt; font-weight:bold; text-align:center;">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</th>
        </tr>
        <tr>
            <th colspan="3" style="font-size:13pt; font-weight:bold; text-align:center;">LAPORAN LABA RUGI</th>
        </tr>
        <tr>
            <th colspan="3" style="font-size:10pt; text-align:center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s.d. {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</th>
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
        {{-- ========== PENDAPATAN ========== --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt; padding-top:10px;">PENDAPATAN USAHA</td>
            <td></td>
        </tr>

        @php $no = 1; @endphp
        @foreach($revenues as $rev)
            @if($rev->period_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $no++ }}</td>
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

        {{-- ========== BEBAN ========== --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">BEBAN USAHA</td>
            <td></td>
        </tr>

        @php $noE = 1; @endphp
        @foreach($expenses as $exp)
            @if($exp->period_balance != 0)
            <tr>
                <td style="text-align:center;">{{ $noE++ }}</td>
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

        {{-- ========== LABA USAHA ========== --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:11pt;">LABA (RUGI) USAHA</td>
            <td style="text-align:right; font-weight:bold; font-size:11pt; border-top:1px solid #000;">{{ number_format($totalRevenue - $totalExpense, 2, ',', '.') }}</td>
        </tr>

        <tr><td colspan="3"></td></tr>

        {{-- ========== PENDAPATAN/BEBAN LAIN-LAIN ========== --}}
        <tr>
            <td></td>
            <td style="font-weight:bold;">Pendapatan (Beban) Lain-Lain</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left:20px; font-style:italic; color:#999;">Belum ada transaksi lain-lain</td>
            <td style="text-align:right;">0,00</td>
        </tr>
        <tr>
            <td></td>
            <td style="font-weight:bold;">Total Pendapatan (Beban) Lain-lain</td>
            <td style="text-align:right; font-weight:bold; border-top:1px solid #000;">0,00</td>
        </tr>

        <tr><td colspan="3"></td></tr>

        {{-- ========== LABA BERSIH ========== --}}
        <tr>
            <td></td>
            <td style="font-weight:bold; font-size:12pt;">{{ $netProfit >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td>
            <td style="text-align:right; font-weight:bold; font-size:12pt; border-top:2px solid #000; border-bottom:3px double #000;">{{ number_format($netProfit, 2, ',', '.') }}</td>
        </tr>

        <tr><td colspan="3"></td></tr>
        <tr><td colspan="3"></td></tr>

        {{-- Footer --}}
        <tr>
            <td colspan="3" style="font-size:8pt; color:#999; text-align:center;">Laporan dihasilkan oleh sistem akuntansi TRUSTA - Dicetak pada: {{ now()->format('d M Y H:i') }}</td>
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
