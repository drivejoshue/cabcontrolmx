<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideMessage extends Model
{
    protected $table = 'ride_messages';

    // Opción 1 (recomendada): lista explícita de columnas nuevas
    protected $fillable = [
        'tenant_id',
        'ride_id',
        'driver_id',
        'passenger_id',

        'sender_type',
        'sender_user_id',

        'kind',
        'template_key',
        'message',
        'meta',

        'seen_by_driver_at',
        'seen_by_dispatch_at',
    ];

    // Si quieres manejar timestamps manualmente, déjalo así:
    public $timestamps = true;
}
