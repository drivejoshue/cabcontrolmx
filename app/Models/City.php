<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name','slug','timezone','center_lat','center_lng','radius_km','is_active'
    ];

    protected $casts = [
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_km'  => 'float',
        'is_active'  => 'boolean',
    ];

    public function places()
    {
        return $this->hasMany(CityPlace::class);
    }
}
