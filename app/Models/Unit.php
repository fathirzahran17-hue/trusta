<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'abbreviation'];

    public function company() { return $this->belongsTo(Company::class); }
}
