<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journal_id', 'account_id', 'project_job_id', 'debit', 'credit', 'description'
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function projectJob(): BelongsTo
    {
        return $this->belongsTo(ProjectJob::class, 'project_job_id');
    }
}
