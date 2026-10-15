<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait EnforcesClosingDate
{
    /**
     * Boot the trait to hook into model events.
     */
    public static function bootEnforcesClosingDate()
    {
        static::saving(function ($model) {
            $model->checkClosingDate();
        });

        static::deleting(function ($model) {
            $model->checkClosingDate();
        });
    }

    /**
     * Check if the model's transaction date is within a closed period.
     * Throws an exception if it is.
     */
    public function checkClosingDate()
    {
        // Don't check if running in console (e.g. seeding) or if user not logged in
        if (app()->runningInConsole() || !auth()->check()) {
            return;
        }

        $company = auth()->user()->company;
        if (!$company || !$company->closing_date) {
            return;
        }

        $dateField = $this->getDateField();
        
        // If the model doesn't have the date field, we can't check
        if (!isset($this->{$dateField})) {
            return;
        }

        $transactionDate = Carbon::parse($this->{$dateField})->startOfDay();
        $closingDate = Carbon::parse($company->closing_date)->startOfDay();

        if ($transactionDate->lte($closingDate)) {
            throw ValidationException::withMessages([
                $dateField => 'Transaksi tidak dapat disimpan karena tanggal berada dalam periode yang sudah Tutup Buku (Closing Date: ' . $closingDate->format('d M Y') . ').'
            ]);
        }
        
        // Also check original date if this is an update, to prevent moving a closed transaction to an open period
        if ($this->exists && $this->isDirty($dateField)) {
            $originalDate = Carbon::parse($this->getOriginal($dateField))->startOfDay();
            if ($originalDate->lte($closingDate)) {
                throw ValidationException::withMessages([
                    $dateField => 'Tidak dapat memodifikasi transaksi yang berada dalam periode yang sudah Tutup Buku.'
                ]);
            }
        }
    }

    /**
     * Determine which field represents the transaction date.
     * Can be overridden by the model using this trait.
     */
    protected function getDateField(): string
    {
        if (isset($this->closingDateField)) {
            return $this->closingDateField;
        }

        // Default guesses based on common table structures
        $guesses = ['transaction_date', 'date', 'invoice_date', 'receipt_date', 'return_date'];
        foreach ($guesses as $guess) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), $guess)) {
                return $guess;
            }
        }

        return 'created_at';
    }
}
