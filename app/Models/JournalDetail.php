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

    protected static function booted(): void
    {
        $check = function (JournalDetail $d) {
            if (!\App\Support\PeriodLock::closingDate()) return;
            $date = Journal::withoutGlobalScopes()->whereKey($d->journal_id)->value('transaction_date');
            \App\Support\PeriodLock::assertOpen($date, 'Jurnal');
        };
        static::creating($check);
        static::updating($check);
        static::deleting($check);
    }

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
