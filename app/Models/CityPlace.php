<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityPlace extends Model
{
    protected $table = 'city_places';

    protected $fillable = [
        'city_id','label','address','lat','lng','category','priority','is_featured','is_active'
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'priority' => 'int',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
