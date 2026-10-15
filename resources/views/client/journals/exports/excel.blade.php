<table>
    <thead>
        <tr>
            <th colspan="6" style="font-size: 16pt; font-weight: bold; text-align: center;">{{ auth()->user()->company->name ?? 'Perusahaan' }}</th>
        </tr>
        <tr>
            <th colspan="6" style="font-size: 14pt; font-weight: bold; text-align: center;">JURNAL UMUM (GENERAL JOURNAL)</th>
        </tr>
        <tr>
            <th colspan="6" style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</th>
        </tr>
        <tr><th colspan="6"></th></tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Tanggal</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">No. Ref</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Kode Akun</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Nama Akun</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Debit</th>
            <th style="font-weight: bold; border: 1px solid #000; background-color: #1a1a2e; color: #ffffff;">Kredit</th>
        </tr>
    </thead>
    <tbody>
        @php $grandDebit = 0; $grandCredit = 0; @endphp
        @foreach($journals as $journal)
            @foreach($journal->details as $i => $detail)
                <tr>
                    @if($i === 0)
                        <td style="border: 1px solid #ccc;">{{ $journal->transaction_date->format('d/m/Y') }}</td>
                        <td style="border: 1px solid #ccc; font-weight: bold;">{{ $journal->ref_number }}</td>
                    @else
                        <td style="border: 1px solid #ccc;"></td>
                        <td style="border: 1px solid #ccc;"></td>
                    @endif
                    <td style="border: 1px solid #ccc;">{{ $detail->account->code ?? '-' }}</td>
                    <td style="border: 1px solid #ccc;">{{ $detail->account->name ?? '-' }}</td>
                    <td style="border: 1px solid #ccc; text-align: right;">{{ $detail->debit > 0 ? number_format($detail->debit, 2, ',', '.') : '' }}</td>
                    <td style="border: 1px solid #ccc; text-align: right;">{{ $detail->credit > 0 ? number_format($detail->credit, 2, ',', '.') : '' }}</td>
                </tr>
                @php
                    $grandDebit += $detail->debit;
                    $grandCredit += $detail->credit;
                @endphp
            @endforeach
            {{-- Keterangan row --}}
            <tr>
                <td style="border: 1px solid #ccc;"></td>
                <td style="border: 1px solid #ccc; font-style: italic; color: #666;" colspan="3">Ket: {{ $journal->description }}</td>
                <td style="border: 1px solid #ccc;"></td>
                <td style="border: 1px solid #ccc;"></td>
            </tr>
            {{-- Separator row --}}
            <tr><td colspan="6"></td></tr>
        @endforeach
        {{-- Grand Total --}}
        <tr>
            <td colspan="4" style="border-top: 2px solid #000; font-weight: bold; text-align: right;">GRAND TOTAL</td>
            <td style="border-top: 2px solid #000; font-weight: bold; text-align: right;">{{ number_format($grandDebit, 2, ',', '.') }}</td>
            <td style="border-top: 2px solid #000; font-weight: bold; text-align: right;">{{ number_format($grandCredit, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
