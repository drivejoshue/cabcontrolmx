<?php  
// app/Models/TenantTaxiCharge.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantTaxiCharge extends Model
{
  protected $fillable = [
    'tenant_id','fee_id','vehicle_id','driver_id','period_type',
    'period_start','period_end','amount','status',
    'generated_at','paid_at','generated_by','paid_by','notes'
  ];

  protected $casts = [
    'period_start' => 'date',
    'period_end'   => 'date',
    'generated_at' => 'datetime',
    'paid_at'      => 'datetime',
  ];

  public function fee(){ return $this->belongsTo(TenantTaxiFee::class, 'fee_id'); }
  public function vehicle(){ return $this->belongsTo(Vehicle::class); }
  public function driver(){ return $this->belongsTo(Driver::class); }
  public function tenant(){ return $this->belongsTo(Tenant::class); }
  public function receipt(){ return $this->hasOne(TenantTaxiReceipt::class, 'charge_id'); }
}
