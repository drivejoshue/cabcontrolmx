<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Model::class => Policy::class,
    ];

    public function boot(): void
    {
        // En versiones con RegistersPolicies, esto está OK:
        $this->registerPolicies();

        Gate::define('admin', function ($user) {
            return (bool) ($user->is_admin ?? false);
        });

         Gate::define('sysadmin', function (User $user) {
            return (bool) $user->is_sysadmin;
        });
    }
}
