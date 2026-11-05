<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Berkas yang dilampirkan pada dokumen. Tersimpan di luar folder publik dan hanya bisa diunduh lewat aplikasi. */
class Attachment extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'attachable_type', 'attachable_id', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    public static function of(string $type, int $id)
    {
        return self::where('attachable_type', $type)->where('attachable_id', $id)->orderBy('id')->get();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
