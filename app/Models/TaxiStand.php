<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxiStand extends Model
{
    use SoftDeletes;

    protected $table = 'taxi_stands';

    protected $fillable = [
        'tenant_id', 'sector_id', 'nombre', 'latitud', 'longitud', 'capacidad', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }
}
