<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'ride_id',
        'rater_type',
        'rater_id',
        'rated_type',
        'rated_id',
        'rating',
        'comment',
        'punctuality',
        'courtesy',
        'vehicle_condition',
        'driving_skills'
    ];

    protected $casts = [
        'rating' => 'integer',
        'punctuality' => 'integer',
        'courtesy' => 'integer',
        'vehicle_condition' => 'integer',
        'driving_skills' => 'integer',
    ];

    // Relación con el ride
    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    // Quién califica
    public function rater()
    {
        return $this->morphTo();
    }

    // Quién es calificado
    public function rated()
    {
        return $this->morphTo();
    }

    // Scope para calificaciones de passenger a driver
    public function scopePassengerToDriver($query)
    {
        return $query->where('rater_type', 'passenger')
                    ->where('rated_type', 'driver');
    }

    // Scope para calificaciones de driver a passenger
    public function scopeDriverToPassenger($query)
    {
        return $query->where('rater_type', 'driver')
                    ->where('rated_type', 'passenger');
    }

    // Scope para un tenant específico
    public function scopeTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}