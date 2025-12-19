<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerDevice extends Model
{
    protected $table = 'passenger_devices';

    protected $fillable = [
        'passenger_id',
        'firebase_uid',
        'device_id',
        'fcm_token',
        'platform',
        'app_version',
        'os_version',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];


    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }
}