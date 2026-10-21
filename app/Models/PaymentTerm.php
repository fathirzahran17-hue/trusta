<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'days', 'description'];
    public function company() { return $this->belongsTo(Company::class); }
}
