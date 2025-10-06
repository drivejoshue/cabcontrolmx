<?php
use App\Http\Middleware\SetTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    }) ->withProviders([
        // Providers del core que sí necesitas
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,

        // Paquetes que uses (opcional)
        // Laravel\Sanctum\SanctumServiceProvider::class,

        // Tus providers (los que EXISTAN)
        App\Providers\AppServiceProvider::class,
        //App\Providers\AuthServiceProvider::class,
        //App\Providers\EventServiceProvider::class,

        // NO pongas RouteServiceProvider en Laravel 12 (no existe por defecto)
        // App\Providers\RouteServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
