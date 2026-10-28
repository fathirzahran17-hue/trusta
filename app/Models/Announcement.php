<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengumuman dari super admin untuk semua klien atau satu perusahaan. */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'level', 'url', 'company_id', 'starts_at', 'ends_at', 'created_by'];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function company() { return $this->belongsTo(Company::class); }

    public function scopeVisibleFor($q, ?int $companyId)
    {
        $today = now()->toDateString();

        return $q->where(fn ($w) => $w->whereNull('company_id')->orWhere('company_id', $companyId))
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }
}
