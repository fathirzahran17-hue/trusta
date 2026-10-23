<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name', 'type', 'normal_balance',
        'opening_balance', 'is_active', 'is_system', 'is_cash_bank', 'is_settlement_deduction',
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
        static::creating(function (Account $a) {
            if (!$a->is_settlement_deduction && self::nameLooksLikeSettlementDeduction((string) $a->name, (string) $a->type)) {
                $a->is_settlement_deduction = true;
            }
        });
        static::saved(function () { self::$parentIdCache = null; \App\Services\EarningsLink::flush(); });
        static::deleted(function () { self::$parentIdCache = null; \App\Services\EarningsLink::flush(); });
    }

    public static function nameLooksLikeCashBank(string $name, string $type): bool
    {
        return $type === 'asset'
            && preg_match('/^(kas|bank)\b/i', trim($name)) === 1
            && !preg_match('/(&|\bdan\b|\bsetara\b)/i', $name);
    }

    /** Akun pajak yang lazim dipotong saat pelunasan: PPh dibayar di muka (aset) / Hutang PPh (kewajiban). */
    public static function nameLooksLikeSettlementDeduction(string $name, string $type): bool
    {
        $n = trim($name);
        if ($type === 'asset') {
            return preg_match('/(pajak dibayar di ?muka|piutang pajak|piutang pph|pph.*dibayar di ?muka|uang muka pajak)/i', $n) === 1;
        }
        if ($type === 'liability') {
            return preg_match('/(hutang|utang)\s+pph(?!\s*(25|29)\b)/i', $n) === 1;
        }

        return false;
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
        'is_cash_bank' => 'boolean',
        'is_settlement_deduction' => 'boolean',
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

    /** Sisi normal menurut jenis akun (aset/biaya = debit, kewajiban/modal/pendapatan = kredit). */
    public function getNaturalSideAttribute(): string
    {
        return in_array($this->type, ['asset', 'expense', 'cost_of_sales', 'other_expense'], true) ? 'debit' : 'credit';
    }

    /** Akun lawan: saldo normalnya berlawanan dengan jenisnya (mis. Akumulasi Penyusutan = aset bersaldo kredit). */
    public function getIsContraAttribute(): bool
    {
        return $this->normal_balance !== $this->natural_side;
    }

    /** Saldo untuk ditampilkan di Bagan Akun: akun lawan tampil minus karena mengurangi kelompoknya. */
    public function getDisplayBalanceAttribute(): float
    {
        return $this->is_contra ? -1 * $this->balance : $this->balance;
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

        // Akun Laba Tahun Berjalan / Laba Ditahan terhubung ke laba rugi (lihat EarningsLink)
        if ($this->type === 'equity') {
            $adj = \App\Services\EarningsLink::creditAdjustment($this);
            if ($adj != 0.0) {
                $ownBalance += $this->normal_balance === 'credit' ? $adj : -$adj;
            }
        }

        // 2. Sum up balances of all children
        $childrenBalance = 0;
        foreach ($this->children as $child) {
            // akun lawan (mis. Akumulasi Penyusutan, saldo normal kredit di bawah induk aset) mengurangi induknya
            $childrenBalance += $child->balance * ($child->normal_balance === $this->normal_balance ? 1 : -1);
        }

        return $ownBalance + $childrenBalance;
    }
}
