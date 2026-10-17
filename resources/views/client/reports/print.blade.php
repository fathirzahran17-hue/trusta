<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $doc['file'] ?? $doc['title'] }}</title>
@php $land = ($doc['orientation'] ?? 'portrait') === 'landscape'; @endphp
<style>
    @page { size: A4 {{ $land ? 'landscape' : 'portrait' }}; margin: 14mm 12mm 16mm;
        @bottom-left { content: "{{ addslashes($company->name ?? '') }}"; font: 8px Arial, sans-serif; color: #64748b; }
        @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); font: 8px Arial, sans-serif; color: #64748b; } }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Helvetica Neue", Arial, sans-serif; font-size: 10px; color: #0f172a; background: #e2e8f0; }
    .bar { position: sticky; top: 0; background: #0f172a; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; justify-content: space-between; font-size: 13px; }
    .bar button, .bar a { background: #0f766e; color: #fff; border: 0; border-radius: 6px; padding: 7px 14px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .bar a { background: #334155; }
    .sheet { background: #fff; width: {{ $land ? '297mm' : '210mm' }}; max-width: 100%; margin: 16px auto; padding: 14mm 12mm; box-shadow: 0 4px 20px rgba(0,0,0,.15); }
    .head { text-align: center; border-bottom: 3px double #0f172a; padding-bottom: 8px; margin-bottom: 10px; }
    .co { font-size: 15px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .sub { font-size: 8.5px; color: #475569; margin-top: 2px; }
    .title { font-size: 13px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; margin-top: 8px; color: #0f766e; }
    .period { font-size: 10px; color: #334155; margin-top: 2px; }
    .sum { display: flex; gap: 8px; margin: 0 0 10px; }
    .sum div { flex: 1; border: 1px solid #cbd5e1; border-radius: 4px; padding: 5px 8px; }
    .sum .l { font-size: 7.5px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: .05em; }
    .sum .v { font-size: 11px; font-weight: 800; margin-top: 1px; }
    .sum .bad .v { color: #b91c1c; } .sum .good .v { color: #047857; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; page-break-inside: avoid; }
    th { background: #0f766e; color: #fff; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; padding: 5px 6px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    th.r, td.r { text-align: right; } td.r { font-variant-numeric: tabular-nums; white-space: nowrap; }
    td { padding: 3.5px 6px; border-bottom: .5px solid #cbd5e1; vertical-align: top; }
    td.neg { color: #b91c1c; }
    tr.section td { background: #e2e8f0; font-weight: 800; padding: 5px 6px; -webkit-print-color-adjust: exact; print-color-adjust: exact; break-after: avoid; }
    tr.subtotal td, tr.total td { font-weight: 800; border-top: .8px solid #475569; }
    tr.total td { background: #f1f5f9; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr.grand td { font-weight: 800; font-size: 11px; background: #ccfbf1; border-top: 1px solid #0f172a; border-bottom: 3px double #0f172a; padding: 6px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr.spacer td { border: 0; padding: 4px; }
    tr.italic td { font-style: italic; color: #64748b; }
    .rpt-empty { text-align: center; padding: 20px; color: #64748b; }
    .rpt-note { margin-top: 10px; padding: 6px 10px; border-radius: 4px; font-weight: 700; font-size: 9px; }
    .rpt-note.good { background: #ecfdf5; color: #047857; } .rpt-note.bad { background: #fef2f2; color: #b91c1c; }
    .foot { margin-top: 14px; font-size: 8px; color: #94a3b8; text-align: right; }
    @media print {
        body { background: #fff; } .bar { display: none; }
        .sheet { box-shadow: none; margin: 0; padding: 0; width: auto; }
    }
</style>
</head>
<body>
<div class="bar">
    <span>Pratinjau PDF — pilih <b>Simpan sebagai PDF</b> pada dialog cetak</span>
    <span><a href="#" onclick="window.close();return false;">Tutup</a> <button onclick="window.print()">Cetak / Simpan PDF</button></span>
</div>
<div class="sheet">
    <div class="head">
        <div class="co">{{ $company->name ?? 'Perusahaan' }}</div>
        @if($company?->address || $company?->npwp)
            <div class="sub">{{ $company->address }}@if($company->npwp) &nbsp;|&nbsp; NPWP: {{ $company->npwp }}@endif</div>
        @endif
        <div class="title">{{ $doc['title'] }}</div>
        <div class="period">{{ $doc['subtitle'] ?? '' }}</div>
    </div>
    @if(!empty($doc['summary']))
        <div class="sum">
            @foreach($doc['summary'] as $s)
                <div class="{{ $s['tone'] ?? '' }}"><div class="l">{{ $s['label'] }}</div>
                <div class="v">{{ ($s['format'] ?? 'money') === 'money' ? 'Rp ' : '' }}{{ \App\Support\ReportFormat::cell($s['value'], $s['format'] ?? 'money') }}</div></div>
            @endforeach
        </div>
    @endif
    @include('client.reports._table', ['doc' => $doc])
    <div class="foot">Dicetak oleh {{ $printedBy }} pada {{ now()->format('d/m/Y H:i') }} &middot; TRUSTA Accounting</div>
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
