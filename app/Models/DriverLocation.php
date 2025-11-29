<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    protected $table = 'driver_locations';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'driver_id', 
        'lat',
        'lng',
        'speed_kmh',
        'bearing',
        'heading_deg',   // 👈 nueva
        'reported_at',
        'created_at'
    ];

    protected $casts = [
        'lat'         => 'float',
        'lng'         => 'float',
        'speed_kmh'   => 'float',
        'bearing'     => 'float',
        'heading_deg' => 'float',  // 👈 nuevo cast
        'reported_at' => 'datetime',
        'created_at'  => 'datetime',
    ];
}
