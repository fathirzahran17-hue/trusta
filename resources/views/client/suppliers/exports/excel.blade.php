<table>
    <thead>
        <tr>
            <th colspan="6" style="font-size: 16pt; font-weight: bold; text-align: center;">{{ auth()->user()->company->name ?? 'Perusahaan' }}</th>
        </tr>
        <tr>
            <th colspan="6" style="font-size: 14pt; font-weight: bold; text-align: center;">BUKU HUTANG PEMASOK</th>
        </tr>
        <tr>
            <th colspan="6" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</th>
        </tr>
        <tr><th colspan="6"></th></tr>
        <tr>
            <th colspan="2" style="font-weight: bold;">Nama Pemasok</th>
            <th colspan="4">{{ $supplier->name }}</th>
        </tr>
        <tr>
            <th colspan="2" style="font-weight: bold;">Kontak</th>
            <th colspan="4">{{ $supplier->phone ?? '-' }} | {{ $supplier->email ?? '-' }}</th>
        </tr>
        <tr>
            <th colspan="2" style="font-weight: bold;">Total Saldo Hutang Saat Ini</th>
            <th colspan="4" style="font-weight: bold;">{{ number_format($totalHutang, 2, ',', '.') }}</th>
        </tr>
        <tr><th colspan="6"></th></tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Tanggal</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">No. Invoice</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Status</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Total Transaksi</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Sudah Dibayar</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Sisa Hutang (Amount Due)</th>
        </tr>
    </thead>
    <tbody>
        @php $sumTotal = 0; $sumPaid = 0; $sumDue = 0; @endphp
        @foreach ($invoices as $inv)
            <tr>
                <td style="border: 1px solid #ccc;">{{ $inv->invoice_date->format('d/m/Y') }}</td>
                <td style="border: 1px solid #ccc;">{{ $inv->invoice_number }}</td>
                <td style="border: 1px solid #ccc;">
                    @if($inv->status == 'paid') Lunas
                    @elseif($inv->status == 'partially_paid') Dibayar Sebagian
                    @else Belum Dibayar @endif
                </td>
                <td style="border: 1px solid #ccc; text-align: right;">{{ $inv->total }}</td>
                <td style="border: 1px solid #ccc; text-align: right;">{{ $inv->amount_paid }}</td>
                <td style="border: 1px solid #ccc; text-align: right; font-weight: bold;">{{ $inv->amount_due }}</td>
            </tr>
            @php
                $sumTotal += $inv->total;
                $sumPaid += $inv->amount_paid;
                $sumDue += $inv->amount_due;
            @endphp
        @endforeach
        {{-- Grand Total --}}
        <tr>
            <td colspan="3" style="border-top: 2px solid #000; font-weight: bold; text-align: right;">TOTAL PERIODE INI</td>
            <td style="border-top: 2px solid #000; font-weight: bold; text-align: right;">{{ $sumTotal }}</td>
            <td style="border-top: 2px solid #000; font-weight: bold; text-align: right;">{{ $sumPaid }}</td>
            <td style="border-top: 2px solid #000; font-weight: bold; text-align: right;">{{ $sumDue }}</td>
        </tr>
    </tbody>
</table>
