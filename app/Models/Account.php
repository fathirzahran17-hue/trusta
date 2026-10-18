<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name', 'type', 'normal_balance',
        'opening_balance', 'is_active', 'is_system', 'is_cash_bank',
    ];

    protected $appends = ['is_header'];

    /** Cache daftar id akun induk; direset setiap akun berubah supaya tidak basi (mis. di tes / proses panjang). */
    private static ?array $parentIdCache = null;

    protected static function booted(): void
    {
        // Akun aset bernama Kas.../Bank... otomatis ditandai kas/bank agar bisa dipakai di Penerimaan/Pembayaran
        static::creating(function (Account $a) {
            if (!$a->is_cash_bank && self::nameLooksLikeCashBank((string) $a->name, (string) $a->type)) {
                $a->is_cash_bank = true;
            }
        });
        static::saved(fn () => self::$parentIdCache = null);
        static::deleted(fn () => self::$parentIdCache = null);
    }

    public static function nameLooksLikeCashBank(string $name, string $type): bool
    {
        return $type === 'asset'
            && preg_match('/^(kas|bank)\b/i', trim($name)) === 1
            && !preg_match('/(&|\bdan\b|\bsetara\b)/i', $name);
    }

    public function getIsHeaderAttribute(): bool
    {
        if (self::$parentIdCache === null) {
            self::$parentIdCache = self::whereNotNull('parent_id')->pluck('parent_id')->unique()->flip()->all();
        }

        // Akun tanpa induk berkode -0000 selalu header utama
        $isRootHeader = (is_null($this->parent_id) && str_ends_with($this->code, '-0000'));

        return isset(self::$parentIdCache[$this->id]) || $isRootHeader;
    }

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }

    /**
     * Hitung saldo berjalan akun (termasuk saldo awal dan transaksi)
     */
    public function getBalanceAttribute(): float
    {
        // 1. Calculate own balance (from its own journal entries)
        $ownDebits = $this->journalDetails()
            ->whereHas('journal', fn($q) => $q->where('status', 'posted'))
            ->sum('debit');
        $ownCredits = $this->journalDetails()
            ->whereHas('journal', fn($q) => $q->where('status', 'posted'))
            ->sum('credit');

        if ($this->normal_balance === 'debit') {
            $ownBalance = $this->opening_balance + $ownDebits - $ownCredits;
        } else {
            $ownBalance = $this->opening_balance + $ownCredits - $ownDebits;
        }

        // 2. Sum up balances of all children
        $childrenBalance = 0;
        foreach ($this->children as $child) {
            $childrenBalance += $child->balance; // recursively calls getBalanceAttribute
        }

        return $ownBalance + $childrenBalance;
    }
}
