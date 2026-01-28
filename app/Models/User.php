<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'default_partner_id',
        'role',
        'active',
        'deactivated_at',
        'disabled_at',
        'is_admin',
        'is_dispatcher',
        'is_sysadmin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',

            'active'             => 'boolean',
            'is_dispatcher'      => 'boolean',
            'is_admin'           => 'boolean',
            'is_sysadmin'        => 'boolean',

            'deactivated_at'     => 'datetime',
            'disabled_at'        => 'datetime',

            'tenant_id'          => 'integer',
            'default_partner_id' => 'integer',

            'sysadmin_totp_secret' => 'encrypted',
    'sysadmin_totp_enabled_at' => 'datetime',
    'sysadmin_totp_confirmed_at' => 'datetime',

            // Tu columna role (string) casteada a enum
            'role'               => \App\Enums\UserRole::class,
        ];
    }

    // -------------------
    // Relaciones core
    // -------------------
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function driver()
    {
        return $this->hasOne(\App\Models\Driver::class, 'user_id', 'id');
    }

    // compatibilidad hacia atrás: $user->isadmin
    public function getIsadminAttribute(): int
    {
        return (int)($this->attributes['is_admin'] ?? 0);
    }

    // -------------------
    // Partner relaciones
    // -------------------
    public function defaultPartner()
    {
        return $this->belongsTo(\App\Models\Partner::class, 'default_partner_id');
    }

    public function partnerMemberships()
    {
        return $this->hasMany(\App\Models\PartnerUser::class, 'user_id');
    }

    // alias (si ya lo estabas usando en otros lados)
    public function partnerUsers()
    {
        return $this->partnerMemberships();
    }

    public function partners()
    {
        return $this->belongsToMany(\App\Models\Partner::class, 'partner_users', 'user_id', 'partner_id')
            ->withPivot(['role','is_primary','invited_at','accepted_at','revoked_at','accepted_at','permissions'])
            ->withTimestamps();
    }

    // -------------------
    // Enum helpers robustos
    // -------------------
    public function roleEnum(): UserRole
    {
        // Si ya viene casteado a enum, regresa tal cual
        if ($this->role instanceof UserRole) {
            return $this->role;
        }

        // Fallback por si en algún contexto viene como string/null
        $raw = $this->attributes['role'] ?? null;
        return UserRole::fromDb(is_string($raw) ? $raw : null);
    }

    // Estos 3 son los que te faltan (y por eso tronaba)
    public function isSysAdminRole(): bool
    {
        return $this->roleEnum() === UserRole::SYSADMIN;
    }

    public function isAdminRole(): bool
    {
        return $this->roleEnum() === UserRole::ADMIN;
    }

    public function isDispatcherRole(): bool
    {
        return $this->roleEnum() === UserRole::DISPATCHER;
    }

    public function isDriverRole(): bool
    {
        return $this->roleEnum() === UserRole::DRIVER;
    }

    // -------------------
    // Flags + enum (reglas canónicas)
    // -------------------
    public function isSysAdmin(): bool
    {
        return (bool)$this->is_sysadmin || $this->isSysAdminRole();
    }

    public function isTenantAdmin(): bool
    {
        return (bool)$this->is_admin || $this->isAdminRole();
    }

    public function isDispatcher(): bool
    {
        return (bool)$this->is_dispatcher || $this->isDispatcherRole();
    }

    public function isDriver(): bool
    {
        return $this->isDriverRole();
    }

    // “partner-only”: no es staff y role NONE
    public function isPartnerOnly(): bool
    {
        $r = $this->roleEnum();
        return !$this->isTenantAdmin() && !$this->isDispatcher() && !$this->isSysAdmin()
            && ($r === UserRole::NONE);
    }

    // -------------------
    // Partner helpers
    // -------------------
    public function isPartnerUser(): bool
    {
        $pid = (int)($this->default_partner_id ?? 0);
        if ($pid > 0) return true;

        return $this->partnerMemberships()->exists();
    }

    /**
     * Indica si el usuario “trae” contexto de partner para redirigir a /partner.
     * Recomendado validar que exista membership vigente para ese default_partner_id.
     */
    public function hasPartnerContext(): bool
    {
        $pid = (int)($this->default_partner_id ?? 0);
        if ($pid <= 0) return false;

        $tid = (int)($this->tenant_id ?? 0);
        if ($tid <= 0) return false;

        return $this->partnerMemberships()
            ->where('tenant_id', $tid)
            ->where('partner_id', $pid)
            ->whereNull('revoked_at')
            ->exists();
    }

    public function partnerRoleFor(int $partnerId): ?string
    {
        $m = $this->partnerMemberships()
            ->where('partner_id', $partnerId)
            ->whereNull('revoked_at')
            ->first();

        return $m?->role;
    }

    public function canAccessPartner(int $partnerId): bool
    {
        if (($this->active ?? true) === false) return false;
        if (!empty($this->disabled_at)) return false;

        $tid = (int)($this->tenant_id ?? 0);
        if ($tid <= 0) return false;

        return $this->partnerMemberships()
            ->where('tenant_id', $tid)
            ->where('partner_id', $partnerId)
            ->whereNull('revoked_at')
            ->exists();
    }

    // -------------------
    // Redirect post-login
    // -------------------
    public function preferredWebHomePath(): string
    {
        if ($this->isSysAdmin()) return '/sysadmin';
        if ($this->isTenantAdmin()) return '/admin';
        if ($this->isDispatcher()) return '/dispatch';

        // Partner panel
        if ($this->hasPartnerContext()) return '/partner';

        // fallback seguro
        return '/login';
    }
}
