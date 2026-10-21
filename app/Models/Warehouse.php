<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'address', 'phone', 'person_in_charge', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function company() { return $this->belongsTo(Company::class); }
}
