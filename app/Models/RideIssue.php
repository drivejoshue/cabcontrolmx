<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideIssue extends Model
{
    
    protected $fillable = [
        'tenant_id','ride_id','passenger_id','driver_id',
        'reporter_type','reporter_user_id',
        'category','title','description','status','severity',
        'forward_to_platform','resolved_at','resolved_by_user_id',
        'resolution_notes','closed_at',
    ];

    protected $casts = [
        'forward_to_platform' => 'boolean',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function ride() { return $this->belongsTo(Ride::class); }
    public function passenger() { return $this->belongsTo(Passenger::class); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function resolvedByUser() { return $this->belongsTo(User::class, 'resolved_by_user_id'); }
    public function notes() { return $this->hasMany(RideIssueNote::class, 'ride_issue_id')->latest(); }

    // Etiquetado “tipo” (ride/app/pago/etc) derivado
    public function scopeLabel(): string
    {
        return match ($this->category) {
            'app_problem' => 'APP',
            'payment', 'overcharge' => 'PAGO',
            'safety' => 'SEGURIDAD',
            'route' => 'RUTA',
            'driver_behavior','passenger_behavior' => 'CONDUCTA',
            'vehicle' => 'VEHÍCULO',
            'lost_item' => 'OBJETO',
            default => 'OTRO',
        };
    }

    // Relaciones
   

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
