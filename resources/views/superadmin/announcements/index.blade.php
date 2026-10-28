<x-app-layout>
    @section('title', 'Pengumuman')
    @section('subtitle', 'Kirim pemberitahuan ke lonceng notifikasi klien (semua atau satu perusahaan)')

    <form method="POST" action="{{ route('superadmin.announcements.store') }}" class="pay-card">
        @csrf
        <div class="pay-card-head"><h3>Pengumuman Baru</h3></div>
        <div class="pay-form-grid">
            <div class="pay-field" style="grid-column: span 2"><label>Judul *</label><input type="text" name="title" maxlength="150" required value="{{ old('title') }}" placeholder="mis. Pemeliharaan sistem Sabtu 22.00"></div>
            <div class="pay-field"><label>Jenis</label><select name="level"><option value="info">Info</option><option value="warning">Penting</option><option value="success">Kabar baik</option></select></div>
            <div class="pay-field"><label>Kepada</label><select name="company_id"><option value="">Semua klien</option>@foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="pay-field" style="grid-column: span 2"><label>Isi</label><textarea name="body" rows="3" maxlength="1000">{{ old('body') }}</textarea></div>
            <div class="pay-field"><label>Tampil mulai</label><input type="date" name="starts_at" value="{{ old('starts_at') }}"></div>
            <div class="pay-field"><label>Tampil sampai</label><input type="date" name="ends_at" value="{{ old('ends_at') }}"></div>
            <div class="pay-field" style="grid-column: span 2"><label>Tautan (opsional)</label><input type="url" name="url" value="{{ old('url') }}" placeholder="https://…"></div>
        </div>
        <div class="pay-foot"><span class="pay-total">Muncul di lonceng notifikasi klien sampai tanggal "tampil sampai" (kosong = selamanya).</span><button type="submit" class="btn-primary">Kirim Pengumuman</button></div>
    </form>

    <div class="pay-card">
        <div class="pay-card-head"><h3>Riwayat Pengumuman</h3></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Dibuat</th><th>Judul</th><th>Kepada</th><th>Periode Tampil</th><th></th></tr></thead>
                <tbody>
                @forelse($items as $a)
                    <tr>
                        <td>{{ $a->created_at->format('d M Y H:i') }}</td>
                        <td class="wrap"><b>{{ $a->title }}</b>@if($a->body)<div style="font-size:.72rem;color:var(--text-dim)">{{ \Illuminate\Support\Str::limit($a->body, 120) }}</div>@endif</td>
                        <td>{{ $a->company ? $a->company->name : 'Semua klien' }}</td>
                        <td>{{ $a->starts_at ? $a->starts_at->format('d M') : 'sekarang' }} – {{ $a->ends_at ? $a->ends_at->format('d M Y') : 'seterusnya' }}</td>
                        <td class="r"><form method="POST" action="{{ route('superadmin.announcements.destroy', $a) }}" onsubmit="return confirm('Hapus pengumuman ini?')">@csrf @method('DELETE')<button class="pay-mini dng" type="submit">Hapus</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="pay-empty">Belum ada pengumuman.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())<div style="padding:10px 18px">{{ $items->links() }}</div>@endif
    </div>
</x-app-layout>
