<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Services\ScheduledRidesService;
use App\Services\OfferBroadcaster;

// =====================================================
// 1) Watchdog: marcar drivers OFFLINE por inactividad
//    - NO cierra turnos
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
    // ✅ Ya NO se autocierra driver_shifts
})->everyMinute()
  ->name('drivers.watchdog.offline')
  ->withoutOverlapping();


// =====================================================
// 2) Disparar rides programados (por tenant) cada minuto
// =====================================================
Schedule::call(function () {
    $tenantIds = DB::table('tenants')->pluck('id')->all();

    // fallback defensivo si no hay tenants (local)
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
// 4) Billing (deja SOLO una estrategia; no dupliques)
// =====================================================

// Diario: tu comando diario
Schedule::command('tenants:billing-daily')
    ->dailyAt('02:10')
    ->name('tenants.billingDaily')
    ->withoutOverlapping();

// Día 1: generar mes completo
Schedule::command('tenants:bill-month-start')
    ->monthlyOn(1, '02:20')
    ->name('tenants.billMonthStart')
    ->withoutOverlapping();


      Schedule::command('orbana:expire-passenger-rides')
    ->everyMinute()
    ->name('orbana.expire_passenger_rides')
    ->withoutOverlapping();


    Schedule::command('orbanamx:autodispatch-tick')
        ->everyMinute()
        ->withoutOverlapping(55)
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/autodispatch_tick.log'));



        Schedule::command('orbana:normalize-runtime')
    ->hourly()
    ->name('orbana.normalize_runtime')
    ->withoutOverlapping(55)
    ->appendOutputTo(storage_path('logs/normalize_runtime.log'));


// Si todavía ocupas tenants:bill, deja SOLO UNO (NO por clase y por firma a la vez)
// Schedule::command('tenants:bill')->dailyAt('03:00')->name('tenants.bill')->withoutOverlapping();


// =====================================================
// 5) Purga chat (si lo reactivas, solo una vez)
// =====================================================
// Schedule::command('chat:purge-old --days=60')
//     ->dailyAt('03:00')
//     ->name('chat.purgeOld')
//     ->withoutOverlapping();




