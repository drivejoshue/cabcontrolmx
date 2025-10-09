<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    protected $fillable = [
        'tenant_id','name','phone','email','default_payment_method','notes',
    ];

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
