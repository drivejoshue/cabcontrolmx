<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $fillable = [
        'tenant_id',
        'economico',
        'plate',
        'brand',
        'model',
        'type',        // 'sedan','vagoneta','van','premium'
        'color',
        'year',
        'capacity',
        'policy_id',
        'photo_url',
        'foto_path',
        'active',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'year'      => 'integer',
        'capacity'  => 'integer',
        'active'    => 'boolean',
    ];

    /* =========================================================
     |  Scopes
     * ========================================================*/
    public function scopeForTenant(Builder $q, int $tenantId): Builder
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $q, bool $active = true): Builder
    {
        return $q->where('active', $active);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $term = trim($term);
        return $q->where(function ($qq) use ($term) {
            $qq->where('economico', 'like', "%{$term}%")
               ->orWhere('plate', 'like', "%{$term}%")
               ->orWhere('brand', 'like', "%{$term}%")
               ->orWhere('model', 'like', "%{$term}%")
               ->orWhere('color', 'like', "%{$term}%");
        });
    }

    /* =========================================================
     |  Relaciones (ajusta nombres si tus modelos difieren)
     * ========================================================*/
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function rides()
    {
        return $this->hasMany(\App\Models\Ride::class);
    }

    // Si manejas asignaciones históricas vehículo-conductor:
    public function assignments()
    {
        return $this->hasMany(\App\Models\DriverVehicleAssignment::class, 'vehicle_id');
    }

    // Asignación actual (si tu tabla tiene ended_at):
    public function currentAssignment()
    {
        return $this->hasOne(\App\Models\DriverVehicleAssignment::class, 'vehicle_id')
            ->whereNull('ended_at')
            ->latestOfMany();
    }

    /* =========================================================
     |  Accessors / Helpers
     * ========================================================*/
    public function getDisplayNameAttribute(): string
    {
        // p.ej. "01615 · YZX-123B · Nissan Versa (Blanco)"
        $parts = array_filter([
            $this->economico,
            $this->plate ? '· '.$this->plate : null,
            trim(implode(' ', array_filter([$this->brand, $this->model]))),
            $this->color ? "({$this->color})" : null,
        ]);
        return trim(implode(' ', $parts)) ?: "Vehículo #{$this->id}";
    }

    public function getEcoPlateAttribute(): string
    {
        // p.ej. "Eco 01615 · YZX-123B"
        $eco = $this->economico ? "Eco {$this->economico}" : null;
        return trim(implode(' · ', array_filter([$eco, $this->plate])));
    }

    public function getPhotoUrlResolvedAttribute(): ?string
    {
        // Prioriza archivo local (foto_path) sobre URL absoluta
        if ($this->foto_path) {
            // Usa storage si lo sirves por /storage
            return asset('storage/'.$this->foto_path);
        }
        return $this->photo_url ?: null;
    }
}
