<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PartnerUser extends Model
{
    protected $table = 'partner_users';

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'user_id',
        'role',
        'is_primary',
        'invited_by',
        'invited_at',
        'accepted_at',
        'revoked_at',
        'permissions',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'invited_at'  => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at'  => 'datetime',
        'permissions' => 'array',
    ];

    // -------- Relationships --------
    public function tenant()  { return $this->belongsTo(Tenant::class); }
    public function partner() { return $this->belongsTo(Partner::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function inviter() { return $this->belongsTo(User::class, 'invited_by'); }

    // -------- Scopes --------

    // Miembro vigente: aceptado y no revocado
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotNull('accepted_at')->whereNull('revoked_at');
    }

    // Miembro principal (por partner)
    public function scopePrimary(Builder $q): Builder
    {
        return $q->where('is_primary', true);
    }

    public function scopeForTenant(Builder $q, int $tenantId): Builder
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeForPartner(Builder $q, int $partnerId): Builder
    {
        return $q->where('partner_id', $partnerId);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    // Helpers de rol
    public function isOwner(): bool { return $this->role === 'owner'; }
    public function isAdmin(): bool { return in_array($this->role, ['owner','admin'], true); }
}
