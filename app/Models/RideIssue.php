<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideIssue extends Model
{
    protected $table = 'ride_issues';

    protected $fillable = [
        'tenant_id',
        'ride_id',
        'passenger_id',
        'driver_id',
        'reporter_type',
        'reporter_user_id',
        'category',
        'title',
        'description',
        'status',
        'severity',
        'forward_to_platform',
        'resolved_at',
    ];

    protected $casts = [
        'forward_to_platform' => 'boolean',
        'resolved_at'         => 'datetime',
    ];

    // Relaciones
    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
