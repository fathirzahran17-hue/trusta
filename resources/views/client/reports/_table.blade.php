@php
    use App\Support\ReportFormat as RF;
    $cols = $doc['columns'];
    $indentCol = $doc['indent_col'] ?? 0;
@endphp
<table class="rpt-table">
    <thead>
        <tr>
            @foreach($cols as $c)
                <th class="{{ ($c['align'] ?? 'left') === 'right' ? 'r' : '' }}" style="{{ ($c['align'] ?? 'left') === 'right' ? 'text-align:right' : '' }}">{{ $c['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($doc['rows'] as $row)
            @php $t = $row['type']; @endphp
            @if($t === 'spacer')
                <tr class="spacer"><td colspan="{{ count($cols) }}"></td></tr>
            @elseif($t === 'section')
                <tr class="section"><td colspan="{{ count($cols) }}">{{ $row['label'] }}</td></tr>
            @else
                <tr class="{{ $t }} {{ !empty($row['italic']) ? 'italic' : '' }}">
                    @foreach($cols as $i => $c)
                        @php
                            $v = $row['cells'][$i] ?? '';
                            $fmt = $c['format'] ?? 'text';
                            $right = ($c['align'] ?? 'left') === 'right';
                            $neg = $fmt === 'money' && is_numeric($v) && $v < 0;
                        @endphp
                        <td class="{{ $right ? 'r' : '' }} {{ $neg ? 'neg' : '' }}"
                            @if($i === $indentCol && ($row['indent'] ?? 0) > 0) style="padding-left: {{ 10 + $row['indent'] * 18 }}px" @endif>{{ RF::cell($v, $fmt) }}</td>
                    @endforeach
                </tr>
            @endif
        @empty
            <tr><td colspan="{{ count($cols) }}" class="rpt-empty">{{ $doc['empty'] ?? 'Tidak ada data.' }}</td></tr>
        @endforelse
    </tbody>
</table>
@foreach($doc['notes'] ?? [] as $note)
    <div class="rpt-note {{ $doc['notes_tone'] ?? 'good' }}">{{ $note }}</div>
@endforeach
