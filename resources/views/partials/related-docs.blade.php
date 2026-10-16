{{-- Panel "Dokumen Terkait". $groups = [['label' => ..., 'items' => [['label','url','meta'], ...]], ...] --}}
@php $groups = collect($groups)->filter(fn ($g) => !empty($g['items']))->values(); @endphp
<div class="related-panel no-print">
    <div class="related-panel-head"><span>Dokumen Terkait</span></div>
    @forelse($groups as $g)
        <div class="related-group">
            <div class="related-group-label">{{ $g['label'] }}</div>
            @foreach($g['items'] as $it)
                @if(!empty($it['url']))
                    <a href="{{ $it['url'] }}" class="related-item"><span class="ri-main">{{ $it['label'] }}</span><span class="ri-meta">{{ $it['meta'] ?? '' }}</span></a>
                @else
                    <div class="related-item"><span>{{ $it['label'] }}</span><span class="ri-meta">{{ $it['meta'] ?? '' }}</span></div>
                @endif
            @endforeach
        </div>
    @empty
        <div class="related-group"><div class="related-empty">Belum ada dokumen yang tertaut.</div></div>
    @endforelse
</div>
