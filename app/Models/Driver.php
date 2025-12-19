<?php

namespace App\Models;
use App\Models\User;


use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
  protected $fillable = [
  'tenant_id','name','phone','email','document_id','status',
  'last_active_status','last_active_at',
  'last_lat','last_lng','last_bearing','last_speed','last_seen_at','user_id',
];


    protected $casts = [
        'last_lat' => 'float',
        'last_lng' => 'float',
        'last_bearing' => 'float',
        'last_speed' => 'float',
        'last_seen_at' => 'datetime',
    ];



  

public function wallet()
{
    return $this->hasOne(DriverWallet::class);
}

public function rides()
{
    return $this->hasMany(Ride::class);
}

public function shifts()
{
    return $this->hasMany(DriverShift::class);
}

public function vehicleAssignments()
{
    return $this->hasMany(DriverVehicleAssignment::class);
}

public function ratings()
{
    return $this->hasMany(Rating::class, 'rated_id')->where('rated_type', 'driver');
}

public function walletMovements()
{
    return $this->hasMany(DriverWalletMovement::class);
}

public function documents()
{
    return $this->hasMany(\App\Models\DriverDocument::class);
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id'); // drivers.user_id -> users.id
}



}
