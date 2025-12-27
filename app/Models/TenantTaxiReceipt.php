<?php
// app/Models/TenantTaxiReceipt.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantTaxiReceipt extends Model
{
  protected $fillable = [
    'tenant_id','charge_id','receipt_number','issued_at','issued_by'
  ];

  protected $casts = [
    'issued_at' => 'datetime',
  ];

  public function charge(){ return $this->belongsTo(TenantTaxiCharge::class, 'charge_id'); }
  public function tenant(){ return $this->belongsTo(Tenant::class); }
}
