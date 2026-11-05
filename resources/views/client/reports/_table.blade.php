@php
    use App\Support\ReportFormat as RF;
    $cols = $doc['columns'];
    $indentCol = $doc['indent_col'] ?? 0;
@endphp
<table class="rpt-table {{ $doc['table_class'] ?? '' }}">
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
                        <td class="{{ $right ? 'r' : '' }} {{ $neg ? 'neg' : '' }} {{ !empty($c['nowrap']) ? 'nowrap' : '' }}"
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
    <div class="rpt-note {{ is_array($note) ? $note['tone'] : ($doc['notes_tone'] ?? 'good') }}">{{ is_array($note) ? $note['text'] : $note }}</div>
@endforeach
@if(!empty($doc['side_by_side']))
    @php $pair = $doc['side_by_side']; @endphp
    <table class="rpt-table rpt-pair">
        <colgroup>
            <col class="pair-col-code"><col><col class="pair-col-value"><col class="pair-col-gap"><col class="pair-col-code"><col><col class="pair-col-value">
        </colgroup>
        <thead>
            <tr class="pair-title"><th colspan="{{ count($pair['columns']) * 2 + 1 }}">{{ $pair['title'] }}</th></tr>
            <tr>
                @foreach(['left', 'right'] as $side)
                    @if($side === 'right')<th class="pair-gap"></th>@endif
                    @foreach($pair['columns'] as $c)
                        <th class="{{ ($c['align'] ?? 'left') === 'right' ? 'r' : '' }}" style="{{ ($c['align'] ?? 'left') === 'right' ? 'text-align:right' : '' }}">{{ $c['label'] }}</th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < max(count($pair['left']), count($pair['right'])); $i++)
                <tr>
                    @foreach(['left', 'right'] as $side)
                        @if($side === 'right')<td class="pair-gap"></td>@endif
                        @php $row = $pair[$side][$i] ?? ['type' => 'spacer']; @endphp
                        @if($row['type'] === 'spacer')
                            <td colspan="{{ count($pair['columns']) }}" class="pair-blank"></td>
                        @elseif($row['type'] === 'section')
                            <td colspan="{{ count($pair['columns']) }}" class="pair-sec">{{ $row['label'] }}</td>
                        @else
                            @foreach($pair['columns'] as $j => $c)
                                @php
                                    $v = $row['cells'][$j] ?? '';
                                    $fmt = $c['format'] ?? 'text';
                                    $right = ($c['align'] ?? 'left') === 'right';
                                    $neg = $fmt === 'money' && is_numeric($v) && $v < 0;
                                @endphp
                                <td class="pair-{{ $row['type'] }} {{ !empty($row['italic']) ? 'pair-italic' : '' }} {{ $right ? 'r' : '' }} {{ $neg ? 'neg' : '' }}"
                                    @if($j === 1 && ($row['indent'] ?? 0) > 0) style="padding-left: {{ 8 + $row['indent'] * 16 }}px" @endif>{{ RF::cell($v, $fmt) }}</td>
                            @endforeach
                        @endif
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
@endif
