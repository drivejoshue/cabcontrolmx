<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // ...
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // No defines Gate admin/sysadmin para evitar inconsistencias.
        // Toda la seguridad web se controla por middleware:
        // - admin => users.isadmin
        // - sysadmin => users.is_sysadmin
    }
}
