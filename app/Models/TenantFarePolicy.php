<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFarePolicy extends Model
{
    protected $table = 'tenant_fare_policies';
    protected $fillable = [
        'tenant_id',
        'mode',
        'base_fee',
        'per_km',
        'per_min',
        'night_start_hour',
        'night_end_hour',
        'round_mode',
        'round_decimals',
        'round_step',
        'night_multiplier',
        'round_to',
        'min_total',
        'stop_fee',
        'extras',
        'slider_min_pct',
        'slider_max_pct',
        'active_from',
        'active_to',
         'extras' => 'array',
  'active_from' => 'date',
  'active_to' => 'date',
    ];

    protected $casts = [
        'tenant_id'        => 'integer',
        'base_fee'         => 'float',
        'per_km'           => 'float',
        'per_min'          => 'float',
        'night_start_hour' => 'integer',
        'night_end_hour'   => 'integer',
        'round_decimals'   => 'integer',
        'round_step'       => 'float',
        'night_multiplier' => 'float',
        'round_to'         => 'float',
        'min_total'        => 'float',
        'stop_fee'         => 'float',

        // ✅ nuevos
        'slider_min_pct'   => 'float',
        'slider_max_pct'   => 'float',

        'extras'           => 'array',
        'active_from'      => 'date',
        'active_to'        => 'date',
         'extras' => 'array',
  'active_from' => 'date',
  'active_to' => 'date',
    ];
}
