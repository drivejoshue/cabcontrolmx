<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    protected $table = 'driver_locations';
    public $timestamps = false;

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'reported_at'=> 'datetime',
    ];
}
