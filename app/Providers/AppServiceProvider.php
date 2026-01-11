<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

use App\Services\TenantBillingService;
use App\Services\TenantWalletService;
use App\Models\Tenant;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('signup', function (Request $request) {
            $email = (string) $request->input('owner_email', '');
            $keyEmail = 'signup:email:' . sha1(mb_strtolower(trim($email)));
            $keyIp    = 'signup:ip:' . $request->ip();

            return [
                Limit::perMinute(5)->by($keyIp),
                Limit::perMinute(3)->by($keyEmail),
            ];
        });

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

        // No tiene sentido ejecutar composers en CLI (cron/artisan)
        if ($this->app->runningInConsole()) {
            return;
        }
View::composer(['layouts.admin*', 'layouts.tabler*', 'layouts.admin_onboarding*', 'layouts.dispatch*',], function ($view) {
    $user = Auth::user();
    if (!$user || !$user->tenant_id) return;

    $tenant = $user->tenant;
    if (!$tenant) return;

    $billing = app(TenantBillingService::class);
    $wallet  = app(TenantWalletService::class);

    $ui = $billing->billingUiState($tenant, $wallet, now());
    $state = $ui['billing_state'] ?? 'ok';

    $shouldBlock = in_array($state, ['action_required', 'grace', 'overdue'], true);

    $isRemediationRoute = request()->routeIs(
        'admin.billing.*',
        'admin.wallet.*',
        'billing.invoices.pay_wallet',
        'admin.billing.accept_terms',
        'logout',
        'login'
    );

    $ui['show_modal'] = $shouldBlock && !$isRemediationRoute;

    $view->with('billingGate', $ui);
});

    }
}
