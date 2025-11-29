<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWalletMovement extends Model
{
    public $timestamps = false; // sólo created_at

    protected $fillable = [
        'driver_id', 'tenant_id', 'ride_id',
        'type', 'direction', 'amount', 'balance_after',
        'description', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
