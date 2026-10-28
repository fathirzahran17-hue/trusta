<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use \App\Traits\EnforcesClosingDate, \App\Models\Concerns\BelongsToCompany;

    protected $guarded = ['id'];
    protected $casts = ['return_date' => 'date'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function salesInvoice() { return $this->belongsTo(SalesInvoice::class); }
    public function items() { return $this->hasMany(SalesReturnItem::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
