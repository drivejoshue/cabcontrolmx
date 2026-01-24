<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $fillable = [
        'tenant_id',

        // partner scope
        'partner_id',
        'recruited_by_partner_id',
        'partner_assigned_at',
        'partner_left_at',
        'partner_notes',

        // core fields
        'economico',
        'plate',
        'brand',
        'model',
        'type',
        'color',
        'year',
        'capacity',
        'policy_id',
        'photo_url',
        'foto_path',
        'active',

        // verification
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'partner_id' => 'integer',
        'recruited_by_partner_id' => 'integer',
        'year' => 'integer',
        'capacity' => 'integer',
        'active' => 'boolean',
        'partner_assigned_at' => 'datetime',
        'partner_left_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /* Scopes */
    public function scopeForTenant(Builder $q, int $tenantId): Builder
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeForPartner(Builder $q, int $tenantId, int $partnerId): Builder
    {
        return $q->where('tenant_id', $tenantId)->where('partner_id', $partnerId);
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

    /* Relaciones */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function recruitedByPartner()
    {
        return $this->belongsTo(Partner::class, 'recruited_by_partner_id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /* Helpers */
    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->economico,
            $this->plate ? '· '.$this->plate : null,
            trim(implode(' ', array_filter([$this->brand, $this->model]))),
            $this->color ? "({$this->color})" : null,
        ]);

        return trim(implode(' ', $parts)) ?: "Vehículo #{$this->id}";
    }

    public function getPhotoUrlResolvedAttribute(): ?string
    {
        if ($this->foto_path) return asset('storage/'.$this->foto_path);
        return $this->photo_url ?: null;
    }
}
