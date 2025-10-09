<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    protected $table = 'rides';

    protected $fillable = [
        'tenant_id',
        'created_by',

        // pasajero
        'passenger_name',
        'passenger_phone',
       

        // origen/destino
        'origin_label', 'origin_lat', 'origin_lng',
        'dest_label',   'dest_lat',   'dest_lng',

        // métrica de ruta / tarifa
        'distance_m', 'duration_s', 'route_polyline',
        'fare_mode', 'payment_method',
        'quoted_amount', 'total_amount', 'currency',
        'fare_snapshot', 'notes', 'pax',

        // programación
        'scheduled_for',

        // asignaciones
        'driver_id', 'vehicle_id', 'shift_id',
        'sector_id', 'stand_id',

        // estado
        'status',
        'requested_channel',

        // timestamps del ciclo
        'accepted_at', 'arrived_at', 'onboard_at', 'finished_at',
        'canceled_at', 'canceled_by', 'cancel_reason',
    ];

    protected $casts = [
        'origin_lat' => 'float',
        'origin_lng' => 'float',
        'dest_lat'   => 'float',
        'dest_lng'   => 'float',
        'distance_m' => 'integer',
        'duration_s' => 'integer',
        'quoted_amount' => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'scheduled_for' => 'datetime',
        'accepted_at'   => 'datetime',
        'arrived_at'    => 'datetime',
        'onboard_at'    => 'datetime',
        'finished_at'   => 'datetime',
        'canceled_at'   => 'datetime',
        'fare_snapshot' => 'array', // si guardas JSON real
        'pax'           => 'integer',
    ];

    /* Scopes útiles */
    public function scopeForTenant($q, $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeSearch($q, $s)
    {
        if (!$s) return;
        $q->where(function($w) use ($s){
            $w->where('passenger_name','like',"%$s%")
              ->orWhere('passenger_phone','like',"%$s%")
              ->orWhere('origin_label','like',"%$s%")
              ->orWhere('dest_label','like',"%$s%");
        });
    }

    public function scopeStatus($q, $status)
    {
        if (!$status) return;
        $q->where('status', $status);
    }
}
