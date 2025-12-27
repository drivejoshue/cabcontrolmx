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



   
    
    public function places(): HasMany
    {
        return $this->hasMany(PassengerPlace::class);
    }
    
    public function devices(): HasMany
    {
        return $this->hasMany(PassengerDevice::class);
    }
    
    // Accesores para privacidad
    public function getMaskedPhoneAttribute(): string
    {
        if (!$this->phone) return 'No disponible';
        return substr($this->phone, 0, 2) . ' **** ' . substr($this->phone, -4);
    }
    
    public function getMaskedEmailAttribute(): string
    {
        if (!$this->email) return 'Sin email';
        $parts = explode('@', $this->email);
        if (count($parts) !== 2) return $this->email;
        return substr($parts[0], 0, 1) . '***' . substr($parts[0], -1) . '@' . $parts[1];
    }
}
