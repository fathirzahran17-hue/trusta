<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $guarded = ['id'];
    protected $casts = ['return_date' => 'date'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
    public function items() { return $this->hasMany(PurchaseReturnItem::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
