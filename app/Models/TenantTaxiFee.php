<?php

// app/Models/TenantTaxiFee.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantTaxiFee extends Model
{
  protected $fillable = [
    'tenant_id','vehicle_id','driver_id','period_type','amount','active','effective_from'
  ];

  protected $casts = [
    'active' => 'bool',
    'effective_from' => 'date',
  ];

  public function vehicle(){ return $this->belongsTo(Vehicle::class); }
  public function driver(){ return $this->belongsTo(Driver::class); }
  public function tenant(){ return $this->belongsTo(Tenant::class); }
}
