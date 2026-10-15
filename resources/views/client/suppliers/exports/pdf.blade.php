<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Buku Hutang - {{ $supplier->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .company-name { font-size: 20px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .report-title { font-size: 16px; font-weight: bold; color: #ef4444; margin-bottom: 5px; }
        .period { font-size: 12px; color: #666; }
        
        .supplier-info { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; background-color: #f9f9f9; }
        .supplier-info h3 { margin: 0 0 5px 0; font-size: 14px; }
        .supplier-info p { margin: 0; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; color: #111827; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .grand-total { border-top: 2px solid #000 !important; background-color: #f3f4f6; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak PDF / Print</button>
        <button onclick="window.close()" style="padding: 8px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Tutup</button>
    </div>

    <div class="header">
        <div class="company-name">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</div>
        <div class="report-title">BUKU HUTANG PEMASOK</div>
        <div class="period">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</div>
    </div>

    <div class="supplier-info">
        <h3>{{ $supplier->name }}</h3>
        <p>Kontak: {{ $supplier->phone ?? '-' }} | {{ $supplier->email ?? '-' }}</p>
        <p>Alamat: {{ $supplier->address ?? '-' }}</p>
        <h4 style="margin: 10px 0 0 0; color: #ef4444;">Total Saldo Hutang Saat Ini: Rp {{ number_format($totalHutang, 2, ',', '.') }}</h4>
    </div>

    <table>
        <thead>
            <tr>
                <th width="12%">Tanggal</th>
                <th width="15%">No. Invoice</th>
                <th width="15%">Status</th>
                <th width="18%" class="text-right">Total Transaksi</th>
                <th width="18%" class="text-right">Sudah Dibayar</th>
                <th width="22%" class="text-right">Sisa Hutang (Amount Due)</th>
            </tr>
        </thead>
        <tbody>
            @php $sumTotal = 0; $sumPaid = 0; $sumDue = 0; @endphp
            @forelse ($invoices as $inv)
                <tr>
                    <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                    <td class="font-bold">{{ $inv->invoice_number }}</td>
                    <td>
                        @if($inv->status == 'paid') Lunas
                        @elseif($inv->status == 'partially_paid') Dibayar Sebagian
                        @else Belum Dibayar @endif
                    </td>
                    <td class="text-right">Rp {{ number_format($inv->total, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($inv->amount_paid, 2, ',', '.') }}</td>
                    <td class="text-right font-bold" style="color: #ef4444;">Rp {{ number_format($inv->amount_due, 2, ',', '.') }}</td>
                </tr>
                @php
                    $sumTotal += $inv->total;
                    $sumPaid += $inv->amount_paid;
                    $sumDue += $inv->amount_due;
                @endphp
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">Tidak ada transaksi hutang pada periode ini.</td>
                </tr>
            @endforelse
            
            @if(count($invoices) > 0)
            <tr>
                <td colspan="3" class="text-right font-bold grand-total" style="font-size: 14px;">TOTAL PERIODE INI</td>
                <td class="text-right font-bold grand-total" style="font-size: 14px;">Rp {{ number_format($sumTotal, 2, ',', '.') }}</td>
                <td class="text-right font-bold grand-total" style="font-size: 14px;">Rp {{ number_format($sumPaid, 2, ',', '.') }}</td>
                <td class="text-right font-bold grand-total" style="font-size: 14px; color: #ef4444;">Rp {{ number_format($sumDue, 2, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #999; text-align: center;">
        <p>Dicetak dari Sistem Akuntansi TRUSTA pada {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
