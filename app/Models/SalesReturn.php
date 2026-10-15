<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = ['id'];
    public function company() { return $this->belongsTo(Company::class); }
    public function salesInvoice() { return $this->belongsTo(SalesInvoice::class); }
    public function items() { return $this->hasMany(SalesReturnItem::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
