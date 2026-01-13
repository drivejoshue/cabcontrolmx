<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
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
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_dispatcher'     => 'boolean',
        'is_admin'          => 'boolean',
        'is_sysadmin'       => 'boolean',
        'role'              => \App\Enums\UserRole::class, // si es enum real
    ];
}

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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

    public function roleEnum(): UserRole
    {
        return UserRole::fromDb($this->role);
    }




public function isDriver(): bool { return $this->role === UserRole::DRIVER; }
public function isAdminRole(): bool { return $this->role === UserRole::ADMIN; }
public function isDispatcherRole(): bool { return $this->role === UserRole::DISPATCHER; }

}
