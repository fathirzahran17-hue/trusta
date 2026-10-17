<x-app-layout>
    @section('title', $doc['title'])
    @section('subtitle', $doc['subtitle'] ?? '')

    @php
        $company = auth()->user()->company;
        $ico = [
            'excel' => 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'pdf' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
            'back' => 'M15 19l-7-7 7-7',
        ];
    @endphp

    <form id="filterForm" method="GET" action="{{ url()->current() }}" class="rpt-toolbar">
        <div class="rpt-filters">
            <a href="{{ route('client.reports.index') }}" class="rpt-btn" title="Kembali ke daftar laporan">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ico['back'] }}"/></svg> Laporan
            </a>
            @foreach($doc['filters'] ?? [] as $f)
                <div class="rpt-field">
                    <label for="f_{{ $f['name'] }}">{{ $f['label'] }}</label>
                    @if($f['type'] === 'select')
                        <select id="f_{{ $f['name'] }}" name="{{ $f['name'] }}">
                            @foreach($f['options'] as $val => $label)
                                <option value="{{ $val }}" @selected((string) $f['value'] === (string) $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="f_{{ $f['name'] }}" type="{{ $f['type'] }}" name="{{ $f['name'] }}" value="{{ $f['value'] }}">
                    @endif
                </div>
            @endforeach
            @if(!empty($doc['filters']))
                <button type="submit" class="rpt-btn primary">Terapkan</button>
            @endif
        </div>
        <div class="rpt-actions">
            <button type="submit" name="export" value="excel" class="rpt-btn excel">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ico['excel'] }}"/></svg> Excel
            </button>
            <button type="submit" name="export" value="pdf" formtarget="_blank" class="rpt-btn pdf">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ico['pdf'] }}"/></svg> PDF / Cetak
            </button>
            <button type="submit" name="export" value="csv" class="rpt-btn">CSV</button>
        </div>
    </form>

    @if(!empty($doc['summary']))
        <div class="rpt-summary">
            @foreach($doc['summary'] as $s)
                <div class="rpt-stat {{ $s['tone'] ?? '' }}">
                    <div class="l">{{ $s['label'] }}</div>
                    <div class="v">{{ ($s['format'] ?? 'money') === 'money' ? 'Rp ' : '' }}{{ \App\Support\ReportFormat::cell($s['value'], $s['format'] ?? 'money') }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="rpt-paper">
        <div class="rpt-head">
            @if($company?->logo_url)<img src="{{ $company->logo_url }}" alt="" class="rpt-logo">@endif
            <div class="rpt-co">{{ $company->name ?? 'Perusahaan' }}</div>
            @if($company?->address || $company?->npwp)
                <div class="rpt-co-sub">{{ $company->address }}@if($company->npwp) &nbsp;|&nbsp; NPWP: {{ $company->npwp }}@endif</div>
            @endif
            <div class="rpt-title">{{ $doc['title'] }}</div>
            <div class="rpt-period">{{ $doc['subtitle'] ?? '' }}</div>
        </div>
        <div class="rpt-body">
            @include('client.reports._table', ['doc' => $doc])
            <div class="rpt-foot">Dicetak oleh {{ auth()->user()->name }} pada {{ now()->format('d/m/Y H:i') }} &middot; TRUSTA Accounting</div>
        </div>
    </div>
</x-app-layout>
