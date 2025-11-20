<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    protected $fillable = [
        'tenant_id',
        'firebase_uid',
        'name',
        'phone',
        'is_corporate',
        'email',
        'default_payment_method',
        'notes',
        'avatar_url',
        'settings',
    ];

    protected $casts = [
        'is_corporate' => 'bool',
        'settings'     => 'array',
    ];

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
