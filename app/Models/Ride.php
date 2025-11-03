<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    protected $table = 'rides';

    public $timestamps = false; // la tabla usa created_at/updated_at DATETIME manuales

    protected $fillable = [
        'tenant_id','passenger_id','requested_channel',
        'passenger_name','passenger_phone',
        'origin_lat','origin_lng','origin_label',
        'dest_lat','dest_lng','dest_label',
        'status','driver_id','vehicle_id','sector_id','stand_id','shift_id',
        'fare_mode','fare_snapshot','total_amount','quoted_amount',
        'allow_bidding','passenger_offer','driver_offer','agreed_amount','bidding_log',
        'currency','payment_method','notes','pax',
        'distance_m','duration_s','route_polyline',
        'scheduled_for','requested_at','accepted_at','arrived_at','onboard_at',
        'finished_at','canceled_at','cancel_reason','canceled_by',
        'created_by','created_at','updated_at',
    ];

    protected $casts = [
    'origin_lat'   => 'float',
    'origin_lng'   => 'float',
    'dest_lat'     => 'float',
    'dest_lng'     => 'float',
    'quoted_amount'=> 'decimal:2',
    'total_amount' => 'decimal:2',
    'distance_m'   => 'int',
    'duration_s'   => 'int',
    'fare_snapshot'=> 'array',
    'bidding_log'  => 'array',
    'scheduled_for'=> 'datetime',
    'requested_at' => 'datetime',
    'accepted_at'  => 'datetime',
    'arrived_at'   => 'datetime',
    'onboard_at'   => 'datetime',
    'finished_at'  => 'datetime',
    'canceled_at'  => 'datetime',
    'created_at'   => 'datetime',
    'updated_at'   => 'datetime',

    // 👇 Estos tres son clave para el front
    'stops_json'   => 'array',
    'stops_count'  => 'int',
    'stop_index'   => 'int',
];


    protected $appends = ['stops'];

    public function getStopsAttribute()
    {
        $v = $this->attributes['stops_json'] ?? null;
        if (is_array($v)) return $v;
        if (is_string($v) && $v !== '') {
            $a = json_decode($v, true);
            return is_array($a) ? $a : [];
        }
        return [];
    }

     public function getScheduledForAttribute($value)
    {
        if (!$value) return null;
        
        // Obtener la zona horaria del tenant
        $tz = $this->tenant->timezone ?? config('app.timezone');
        
        return \Carbon\Carbon::parse($value)->timezone($tz);
    }

    /**
     * Convertir scheduled_for a la zona del tenant al guardar
     */
    public function setScheduledForAttribute($value)
    {
        if (!$value) {
            $this->attributes['scheduled_for'] = null;
            return;
        }

        if (is_string($value)) {
            // Si ya es string, asumimos que está en la zona correcta
            $this->attributes['scheduled_for'] = $value;
        } else {
            // Si es Carbon, convertir a string en la zona del tenant
            $tz = $this->tenant->timezone ?? config('app.timezone');
            $this->attributes['scheduled_for'] = $value->timezone($tz)->format('Y-m-d H:i:s');
        }
    }


    public const ST_QUEUED = 'queued';

    public function scopeOfferable($q) {
      return $q->whereNull('driver_id')
               ->whereIn('status', ['requested','offered','queued']);
    }

    public function scopeCancelable($q) {
      return $q->whereIn('status', [
        'requested','offered','queued','accepted','en_route','arrived','on_board'
      ]);
    }

    public function statusHistory()
    {
        return $this->hasMany(RideStatusHistory::class, 'ride_id')->orderBy('created_at');
    }
}
