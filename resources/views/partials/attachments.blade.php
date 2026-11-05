{{-- Lampiran dokumen. Param: $attachType (kunci di AttachmentController::TYPES), $attachId --}}
@php
    $attachCompany = auth()->user()->company;
    $attachFiles = \App\Models\Attachment::of($attachType, $attachId);
    $attachAllowed = \App\Http\Controllers\Client\AttachmentController::allowedTypes($attachCompany?->allowed_attachment_types);
    $attachMax = min(max(1, (int) ($attachCompany?->max_attachment_size ?? 5)), 10);
@endphp
<div class="attach-panel no-print">
    <div class="attach-head">Lampiran</div>
    @forelse($attachFiles as $file)
        <div class="attach-row">
            <a href="{{ route('client.attachments.download', $file) }}" class="attach-name">{{ $file->original_name }}</a>
            <span class="attach-size">{{ number_format($file->size / 1024, 0, ',', '.') }} KB</span>
            <form method="POST" action="{{ route('client.attachments.destroy', $file) }}" data-no-guard onsubmit="return confirm('Hapus lampiran ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="attach-del" aria-label="Hapus lampiran {{ $file->original_name }}">Hapus</button>
            </form>
        </div>
    @empty
        <p class="attach-empty">Belum ada lampiran.</p>
    @endforelse
    <form method="POST" action="{{ route('client.attachments.store', [$attachType, $attachId]) }}" enctype="multipart/form-data" class="attach-form" data-no-guard>
        @csrf
        <input type="file" name="file" required aria-label="Pilih berkas lampiran" accept="{{ collect($attachAllowed)->map(fn ($t) => '.' . strtolower($t))->implode(',') }}">
        <button type="submit" class="btn-secondary">Unggah</button>
        <p class="field-hint">Jenis: {{ implode(', ', $attachAllowed) }}. Maksimal {{ $attachMax }} MB.</p>
    </form>
</div>
