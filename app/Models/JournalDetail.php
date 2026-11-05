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
            // Baris jurnal tidak boleh memakai akun perusahaan lain, dari jalur mana pun datangnya
            $user = auth()->hasUser() ? auth()->user() : null;
            if ($user && $user->company_id && $user->role !== 'super_admin'
                && ! Account::withoutGlobalScopes()->whereKey($d->account_id)->where('company_id', $user->company_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['account_id' => 'Akun tidak ditemukan.']);
            }
            if (!\App\Support\PeriodLock::closingDate() && !\App\Support\PeriodLock::startDate()) return;
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
