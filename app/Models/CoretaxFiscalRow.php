<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kolom fiskal Lampiran 1 yang diisi pengguna untuk satu kode akun pada satu tahun pajak. */
class CoretaxFiscalRow extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'tax_year', 'code', 'non_object', 'final_income', 'positive', 'negative', 'adjustment_codes'];
}
