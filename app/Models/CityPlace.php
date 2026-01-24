<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityPlace extends Model
{
    protected $table = 'city_places';

    protected $fillable = [
        'city_id',
        'label',
        'address',
        'lat',
        'lng',
        'category',
        'priority',
        'is_featured',
        'is_active',

        // ✅ Tarifa especial por lugar
        'fare_is_active',
        'fare_radius_m',
        'fare_near_origin_radius_m',
        'fare_rule',
    ];

    protected $casts = [
        'city_id'   => 'integer',
        'lat'       => 'float',
        'lng'       => 'float',
        'priority'  => 'integer',

        'is_featured' => 'boolean',
        'is_active'   => 'boolean',

        // ✅ Tarifa especial
        'fare_is_active'            => 'boolean',
        'fare_radius_m'             => 'integer',
        'fare_near_origin_radius_m' => 'integer',

        // Si la columna es JSON, esto es lo correcto.
        // Si tu columna fuera TEXT/VARCHAR, esto igual ayuda porque serializa/deserializa.
        'fare_rule' => 'array',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
