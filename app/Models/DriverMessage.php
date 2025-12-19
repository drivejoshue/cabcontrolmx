<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DriverMessage extends Model
{
    use HasFactory;

    protected $table = 'driver_messages';

    /**
     * Clave primaria (por claridad, aunque es el default).
     */
    protected $primaryKey = 'id';

    /**
     * IDs autoincrement BigInt sin cast especial (Laravel ya lo maneja).
     */
    protected $keyType = 'int';

    /**
     * Timestamps activados (created_at / updated_at).
     */
    public $timestamps = true;

    /**
     * Campos asignables en masa según la tabla driver_messages.
     */
    protected $fillable = [
        'tenant_id',
        'ride_id',
        'driver_id',
        'passenger_id',

        'sender_type',      // 'driver','dispatch','passenger','system'
        'sender_user_id',

        'kind',             // 'chat','help','warning','info'
        'template_key',
        'message',
        'meta',

        'seen_by_driver_at',
        'seen_by_dispatch_at',
    ];

    /**
     * Casts de columnas especiales.
     */
    protected $casts = [
        'meta'               => 'array',
        'seen_by_driver_at'  => 'datetime',
        'seen_by_dispatch_at'=> 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones útiles (opcionales pero recomendables)
    |--------------------------------------------------------------------------
    */

    public function driver()
    {
        return $this->belongsTo(\App\Models\Driver::class);
    }

    public function passenger()
    {
        return $this->belongsTo(\App\Models\Passenger::class);
    }

    public function ride()
    {
        return $this->belongsTo(\App\Models\Ride::class);
    }
}
