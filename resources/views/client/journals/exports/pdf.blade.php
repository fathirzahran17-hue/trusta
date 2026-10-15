<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jurnal Umum</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .company-name { font-size: 20px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .report-title { font-size: 16px; font-weight: bold; color: #14b8a6; margin-bottom: 5px; }
        .period { font-size: 12px; color: #666; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; color: #111827; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-center { text-align: center; }
        
        .journal-row { background-color: #fcfcfc; }
        .detail-row td { border-top: none; }
        .desc-row td { border-top: none; font-style: italic; color: #666; padding-top: 2px; padding-bottom: 8px; }
        .separator td { border: none; border-bottom: 1px solid #ddd; padding: 4px; }
        
        .grand-total { border-top: 2px solid #000 !important; background-color: #f3f4f6; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background-color: #14b8a6; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak PDF / Print</button>
        <button onclick="window.close()" style="padding: 8px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Tutup</button>
    </div>

    <div class="header">
        <div class="company-name">{{ auth()->user()->company->name ?? 'Perusahaan Saya' }}</div>
        <div class="report-title">JURNAL UMUM (GENERAL JOURNAL)</div>
        <div class="period">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d F Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="12%">Tanggal</th>
                <th width="15%">No. Ref</th>
                <th width="12%">Kode Akun</th>
                <th width="25%">Nama Akun</th>
                <th width="18%" class="text-right">Debit</th>
                <th width="18%" class="text-right">Kredit</th>
            </tr>
        </thead>
        <tbody>
            @php $grandDebit = 0; $grandCredit = 0; @endphp
            @forelse($journals as $journal)
                @foreach($journal->details as $i => $detail)
                    <tr class="{{ $i === 0 ? 'journal-row' : 'detail-row' }}">
                        @if($i === 0)
                            <td style="vertical-align: top;">{{ $journal->transaction_date->format('d/m/Y') }}</td>
                            <td style="vertical-align: top;" class="font-bold">{{ $journal->ref_number }}</td>
                        @else
                            <td style="border-bottom: none;"></td>
                            <td style="border-bottom: none;"></td>
                        @endif
                        
                        <td>{{ $detail->account->code ?? '-' }}</td>
                        <td>{{ $detail->account->name ?? '-' }}</td>
                        <td class="text-right">{{ $detail->debit > 0 ? 'Rp ' . number_format($detail->debit, 2, ',', '.') : '' }}</td>
                        <td class="text-right">{{ $detail->credit > 0 ? 'Rp ' . number_format($detail->credit, 2, ',', '.') : '' }}</td>
                    </tr>
                    @php
                        $grandDebit += $detail->debit;
                        $grandCredit += $detail->credit;
                    @endphp
                @endforeach
                
                {{-- Keterangan Jurnal --}}
                <tr class="desc-row">
                    <td></td>
                    <td colspan="3">Ket: {{ $journal->description }}</td>
                    <td></td>
                    <td></td>
                </tr>
                
                {{-- Separator --}}
                <tr class="separator"><td colspan="6"></td></tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">Tidak ada jurnal pada periode ini.</td>
                </tr>
            @endforelse
            
            @if(count($journals) > 0)
            <tr>
                <td colspan="4" class="text-right font-bold grand-total" style="font-size: 14px;">GRAND TOTAL</td>
                <td class="text-right font-bold grand-total" style="font-size: 14px; color: #14b8a6;">Rp {{ number_format($grandDebit, 2, ',', '.') }}</td>
                <td class="text-right font-bold grand-total" style="font-size: 14px; color: #14b8a6;">Rp {{ number_format($grandCredit, 2, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #999; text-align: center;">
        <p>Dicetak dari Sistem Akuntansi TRUSTA pada {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
