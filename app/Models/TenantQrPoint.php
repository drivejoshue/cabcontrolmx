<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantQrPoint extends Model
{
  protected $fillable = [
    'tenant_id','name','code','address_text','lat','lng','active','meta'
  ];

  protected $casts = [
    'active' => 'boolean',
    'meta' => 'array',
    'lat' => 'float',
    'lng' => 'float',
  ];

  public function tenant() { return $this->belongsTo(Tenant::class); }
}