<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class Tenant extends Model
{
protected $table = 'tenants';
protected $fillable = ['name','slug','timezone','utc_offset_minutes','latitud','longitud','allow_marketplace'];
protected $casts = [
'allow_marketplace' => 'boolean',
'utc_offset_minutes'=> 'integer',
'latitud' => 'float',
 'coverage_radius_km' => 'float',
'longitud' => 'float',
];

public function billingProfile()
{
    return $this->hasOne(TenantBillingProfile::class);
}

public function invoices()
{
    return $this->hasMany(TenantInvoice::class);
}


}