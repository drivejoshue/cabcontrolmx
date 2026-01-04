<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
     use HasApiTokens, Notifiable;  

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
         'name',
        'email',
        'password',
        'tenant_id',
        'is_admin',
        'is_dispatcher',
        'is_sysadmin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
             'is_dispatcher' => 'boolean',
            'is_admin' => 'boolean',
            'is_sysadmin'       => 'boolean',
        ];
    }
     public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }


    // App\Models\User.php

public function getIsadminAttribute()
{
    // compatibilidad hacia atrás: $user->isadmin
    return (int) ($this->attributes['is_admin'] ?? 0);
}


   public function driver()
{
    return $this->hasOne(\App\Models\Driver::class, 'user_id', 'id');
}

}
