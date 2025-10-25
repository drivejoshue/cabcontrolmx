<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideStatusHistory extends Model
{
    protected $table = 'ride_status_history';
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'meta'       => 'array',
    ];
}
