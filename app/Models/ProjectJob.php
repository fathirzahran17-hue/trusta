<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectJob extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function journalDetails()
    {
        return $this->hasMany(JournalDetail::class, 'project_job_id');
    }
}
