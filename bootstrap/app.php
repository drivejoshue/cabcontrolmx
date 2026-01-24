<?php

use App\Http\Middleware\EnsureTenantOnboarded;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSysAdmin;
use App\Http\Middleware\SetTenantFromUser;
use App\Http\Middleware\PartnerBillingGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->appendToGroup('web', SetTenantFromUser::class);
        $middleware->append(HandleCors::class);

        $middleware->alias([
            'admin'           => EnsureUserIsAdmin::class,
            'sysadmin'        => EnsureUserIsSysAdmin::class,
            'tenant.onboarded'=> EnsureTenantOnboarded::class,
            'tenant.ready' => \App\Http\Middleware\EnsureTenantReady::class,
            'tenant.billing_ok_api' => \App\Http\Middleware\TenantBillingOkApi::class,
            'dispatch'  => \App\Http\Middleware\EnsureUserCanDispatch::class,
            'tenant.balance' => \App\Http\Middleware\EnsureTenantSufficientBalance::class,
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'public.key' => \App\Http\Middleware\PublicApiKeyMiddleware::class,
            'orbana.core' => \App\Http\Middleware\OrbanaCoreOnly::class,
            'partner.ctx' => \App\Http\Middleware\EnsurePartnerContext::class,
            'partner.billing.gate' => \App\Http\Middleware\PartnerBillingGate::class,


        ]);
    })
    ->withProviders([
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        App\Providers\AppServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (TokenMismatchException $e, $request) {

            // Para fetch/AJAX y APIs: responde JSON 419 en vez de HTML "Page Expired"
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'session_expired',
                ], 419);
            }

            // Web: limpia sesión y manda a login (evita quedarse atorado)
            try {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } catch (\Throwable $t) {
                // si ya no hay sesión activa, ignorar
            }

            if (\Illuminate\Support\Facades\Route::has('login')) {
                return redirect()->route('login')
                    ->with('status', 'Tu sesión expiró. Inicia sesión de nuevo.');
            }

            return redirect('/login')
                ->with('status', 'Tu sesión expiró. Inicia sesión de nuevo.');
        });

    })
    ->create();
