<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'tenant_id','name','phone','email','document_id','status',
        'last_lat','last_lng','last_bearing','last_speed','last_seen_at','user_id',
    ];

    protected $casts = [
        'last_lat' => 'float',
        'last_lng' => 'float',
        'last_bearing' => 'float',
        'last_speed' => 'float',
        'last_seen_at' => 'datetime',
    ];



     public function user()   { return $this->belongsTo(\App\Models\User::class); }
    public function tenant() { return $this->belongsTo(\App\Models\Tenant::class); }

public function wallet()
{
    return $this->hasOne(DriverWallet::class);
}

public function walletMovements()
{
    return $this->hasMany(DriverWalletMovement::class);
}

}
