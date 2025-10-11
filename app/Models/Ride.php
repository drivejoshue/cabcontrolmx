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
        'fare_snapshot'=> 'array',   // JSON
        'bidding_log'  => 'array',   // JSON
        'scheduled_for'=> 'datetime',
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'arrived_at'   => 'datetime',
        'onboard_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'canceled_at'  => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
