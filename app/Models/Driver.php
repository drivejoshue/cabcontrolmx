<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'tenant_id','name','phone','email','document_id','status',
        'last_lat','last_lng','last_bearing','last_speed','last_seen_at',
    ];

    protected $casts = [
        'last_lat' => 'float',
        'last_lng' => 'float',
        'last_bearing' => 'float',
        'last_speed' => 'float',
        'last_seen_at' => 'datetime',
    ];
}
