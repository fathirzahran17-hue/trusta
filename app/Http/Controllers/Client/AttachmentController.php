<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /** Jenis dokumen yang boleh diberi lampiran. Global scope model membatasi ke perusahaan pengguna. */
    public const TYPES = [
        'sales-invoice' => \App\Models\SalesInvoice::class,
        'purchase-invoice' => \App\Models\PurchaseInvoice::class,
        'sales-order' => \App\Models\SalesOrder::class,
        'purchase-order' => \App\Models\PurchaseOrder::class,
        'journal' => \App\Models\Journal::class,
    ];

    /** Jenis yang bisa dipilih di Preferensi. Isi berkas dicek agar nama berkas tidak bisa menyamar. */
    public const SAFE_TYPES = ['PDF', 'JPG', 'JPEG', 'PNG', 'WEBP', 'XLSX', 'XLS', 'DOCX', 'DOC', 'CSV', 'TXT'];
    private const MUST_MATCH = ['pdf' => ['pdf'], 'jpg' => ['jpg', 'jpeg'], 'jpeg' => ['jpg', 'jpeg'], 'png' => ['png'], 'webp' => ['webp']];
    private const HARD_LIMIT_MB = 10; // batas upload hosting

    public function store(Request $request, string $type, int $id)
    {
        $model = self::TYPES[$type] ?? abort(404);
        $owner = $model::findOrFail($id);
        $company = auth()->user()->company;
        $maxMb = min(max(1, (int) $company->max_attachment_size), self::HARD_LIMIT_MB);
        $allowed = self::allowedTypes($company->allowed_attachment_types);

        $request->validate(['file' => 'required|file|max:' . ($maxMb * 1024)], [
            'file.required' => 'Pilih berkas yang akan dilampirkan.',
            'file.max' => "Ukuran lampiran maksimal {$maxMb} MB.",
            'file.uploaded' => "Berkas gagal diunggah. Ukuran maksimal {$maxMb} MB.",
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $guessed = strtolower((string) $file->extension());
        $matches = ! isset(self::MUST_MATCH[$ext]) || in_array($guessed, self::MUST_MATCH[$ext], true);
        if (! in_array(strtoupper($ext), $allowed, true) || ! $matches) {
            return back()->withErrors(['file' => 'Jenis berkas tidak diizinkan. Yang boleh: ' . implode(', ', $allowed) . '.']);
        }

        $path = $file->storeAs('attachments/' . $owner->company_id, bin2hex(random_bytes(16)) . '.' . $ext, 'local');
        Attachment::create([
            'company_id' => $owner->company_id, 'attachable_type' => $type, 'attachable_id' => $owner->id,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'path' => $path,
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Lampiran ditambahkan.');
    }

    public function download(Attachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    /** @return string[] */
    public static function allowedTypes(?string $setting): array
    {
        $list = array_values(array_intersect(array_map(fn ($t) => strtoupper(trim($t)), explode(',', (string) $setting)), self::SAFE_TYPES));
        if (in_array('JPG', $list, true) && ! in_array('JPEG', $list, true)) {
            $list[] = 'JPEG';
        }

        return $list ?: ['PDF', 'JPG', 'JPEG', 'PNG'];
    }
}
