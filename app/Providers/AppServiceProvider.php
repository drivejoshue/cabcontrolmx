<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

use App\Models\Tenant;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Rate limit signup
        RateLimiter::for('signup', function (Request $request) {
            $email = (string) $request->input('owner_email', '');
            $keyEmail = 'signup:email:' . sha1(mb_strtolower(trim($email)));
            $keyIp    = 'signup:ip:' . $request->ip();

            return [
                Limit::perMinute(5)->by($keyIp),
                Limit::perMinute(3)->by($keyEmail),
            ];
        });

        // Listener al verificar email
        Event::listen(Verified::class, function (Verified $event) {
            $user = $event->user;
            if (!$user || !(int)($user->tenant_id ?? 0)) return;

            Tenant::where('id', $user->tenant_id)
                ->where('public_active', 0)
                ->update(['public_active' => 1]);
        });

        RateLimiter::for('public-contact', function ($request) {
    return [
        Limit::perMinute(10)->by($request->ip()),
        Limit::perHour(100)->by($request->ip()),
    ];
});
    }
}





