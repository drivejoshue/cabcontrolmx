<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassengerPlace extends Model
{
    protected $table = 'passenger_places';

    protected $fillable = [
        'passenger_id','city_id','kind','slot','label','address','lat','lng',
        'is_active','last_used_at','use_count'
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'slot' => 'int',
        'use_count' => 'int',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
