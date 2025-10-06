<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sector extends Model
{
    use SoftDeletes;

    protected $table = 'sectores';

    protected $fillable = [
        'tenant_id', 'nombre', 'area', 'activo',
    ];

    protected $casts = [
        'area' => 'array',   // GeoJSON guardado como JSON
        'activo' => 'boolean',
    ];

    public function stands()
    {
        return $this->hasMany(TaxiStand::class, 'sector_id');
    }
}
