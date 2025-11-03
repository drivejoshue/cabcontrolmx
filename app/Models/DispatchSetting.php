<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchSetting extends Model
{
    protected $table = 'dispatch_settings';
    protected $primaryKey = 'id';
    public $timestamps = true;

    // Rellenables (ajusta si necesitas menos/más)
    protected $fillable = [
        'tenant_id',
        'auto_dispatch_radius_km',
        'nearby_search_radius_km',
        'stand_radius_km',
        'offer_expires_sec',
        'wave_size_n',
        'lead_time_min',
        'use_google_for_eta',
        'allow_fare_bidding',
        'extras',
        'auto_enabled',
        'auto_delay_sec',
        'auto_assign_if_single',
        'auto_dispatch_delay_s',
        'auto_dispatch_preview_n',
         'stop_fee',
           // 👇 nuevos
        'max_queue',
        'queue_sla_minutes',
        'central_priority',
    ];

    protected $casts = [
        'tenant_id'                   => 'integer',
        'auto_dispatch_radius_km'     => 'float',
        'nearby_search_radius_km'     => 'float',
        'stand_radius_km'             => 'float',
        'offer_expires_sec'           => 'integer',
        'wave_size_n'                 => 'integer',
        'lead_time_min'               => 'integer',
        'use_google_for_eta'          => 'boolean',
        'allow_fare_bidding'          => 'boolean',
        'extras'                      => 'array',
        'auto_enabled'                => 'boolean',
        'auto_delay_sec'              => 'integer',
        'auto_assign_if_single'       => 'boolean',
        'auto_dispatch_delay_s'       => 'integer',
        'auto_dispatch_preview_n'     => 'integer',
         'stop_fee'                         => 'float',
          // 👇 nuevos
        'max_queue'                => 'integer',
        'queue_sla_minutes'        => 'integer',
        'central_priority'         => 'boolean',
    ];

    // Única por tenant (ya hay UNIQUE en la tabla)
    public function scopeForTenant($q, $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }
}
