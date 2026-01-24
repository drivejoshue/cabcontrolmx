<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Services\ScheduledRidesService;
use App\Services\OfferBroadcaster;

// =====================================================
// 1) Watchdog: marcar drivers OFFLINE por inactividad
// =====================================================
Schedule::call(function () {
    $cut = now()->subMinutes(10);

    $activeDriverIds = DB::table('driver_locations')
        ->whereNotNull('reported_at')
        ->where('reported_at', '>=', $cut)
        ->select('driver_id')
        ->distinct();

    DB::table('drivers')
        ->where('status', '!=', 'offline')
        ->whereNotIn('id', $activeDriverIds)
        ->update([
            'status' => 'offline',
            'updated_at' => now(),
        ]);
})->everyMinute()
  ->name('drivers.watchdog.offline')
  ->withoutOverlapping();

// =====================================================
// 2) Disparar rides programados
// =====================================================
Schedule::call(function () {
    $tenantIds = DB::table('tenants')->pluck('id')->all();
    if (empty($tenantIds)) $tenantIds = [1];

    foreach ($tenantIds as $tenId) {
        try {
            ScheduledRidesService::fireDue((int)$tenId);
        } catch (\Throwable $e) {
            Log::warning('rides.fireScheduled failed', [
                'tenant' => (int)$tenId,
                'msg'    => $e->getMessage(),
            ]);
        }
    }
})->everyMinute()
  ->name('rides.fireScheduled')
  ->withoutOverlapping();

// =====================================================
// 3) Dispatch Ticks - Sistema de Colas Mejorado
// =====================================================

// A) Tick RÁPIDO de procesamiento de colas (5 segundos)
// Schedule::command('orbanamx:dispatch-tick')
//     ->everyFiveSeconds()
//     ->withoutOverlapping(10)
//     ->runInBackground()
//     ->name('dispatch.tick.fast')
//     ->appendOutputTo(storage_path('logs/dispatch_tick_fast.log'));

// B) Tick de bootstrap (crea tracks nuevos) - cada minuto
Schedule::command('orbanamx:autodispatch-tick --limit=100')
    ->everyMinute()
    ->withoutOverlapping(55)
    ->runInBackground()
    ->name('dispatch.bootstrap')
    ->appendOutputTo(storage_path('logs/dispatch_bootstrap.log'));



// =====================================================
// 4) Expiración de rides (MODIFICADO para evitar interferencia)
// =====================================================
// Solo expirar passenger rides, NO los de dispatch
Schedule::command('orbana:expire-passenger-rides')
    ->everyMinute()
    ->withoutOverlapping(55)
    ->runInBackground()
    ->name('expire.passenger.rides')
    ->appendOutputTo(storage_path('logs/expire_passenger_rides.log'));

// =====================================================
// 5) Billing
// =====================================================
Schedule::command('tenants:billing-daily')
    ->dailyAt('02:10')
    ->name('tenants.billingDaily')
    ->withoutOverlapping();

Schedule::command('tenants:bill-month-start')
    ->monthlyOn(1, '02:20')
    ->name('tenants.billMonthStart')
    ->withoutOverlapping();

Schedule::command('billing:suspend-overdue --days=0')
    ->dailyAt('02:15')
    ->name('billing.suspend_overdue')
    ->withoutOverlapping();

// =====================================================
// 6) Mantenimiento y limpieza
// =====================================================
Schedule::command('orbana:normalize-runtime')
    ->hourly()
    ->withoutOverlapping(55)
    ->name('orbana.normalize_runtime')
    ->appendOutputTo(storage_path('logs/normalize_runtime.log'));

Schedule::command('chat:purge-old --days=30')
    ->dailyAt('03:00')
    ->name('chat.purgeOld')
    ->withoutOverlapping();

Schedule::command('orbanamx:partner-prepaid-daily')
    ->hourly()
    ->name('partner.prepaid.daily')
    ->withoutOverlapping();

     
Schedule::command('dispatch:outbox-offernew --sleep=50 --limit=300')
        ->everySecond()
        ->name('dispatch.outbox.offernew')
        ->withoutOverlapping();