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
    $now = now();

    // Ajusta según tu operación
    $offlineAfterMinutes = 30;
    $cutOffOffline = now()->subMinutes($offlineAfterMinutes);

    // OJO: aquí sigues usando last_seen_at.
    // Si ya migraste a driver_locations para "fresh", cambia esta lógica a driver_locations.
    DB::table('drivers')
        ->where(function ($q) use ($cutOffOffline) {
            $q->whereNull('last_seen_at')
              ->orWhere('last_seen_at', '<', $cutOffOffline);
        })
        ->where('status', '!=', 'offline')
        ->update([
            'status'     => 'offline',
            'updated_at' => $now,
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
// 3) Expirar ofertas vencidas + emitir realtime
//    - SOLO una versión (no uses también el Command)
// =====================================================
Schedule::call(function () {
    $now = now();

    DB::table('ride_offers')
        ->select('id','tenant_id','driver_id','ride_id')
        ->where('status', 'offered')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', $now)
        ->orderBy('id')
        ->chunkById(500, function ($rows) use ($now) {
            foreach ($rows as $r) {
                // anti-race: solo si sigue offered
                $updated = DB::table('ride_offers')
                    ->where('id', $r->id)
                    ->where('status', 'offered')
                    ->update([
                        'status'       => 'expired',
                        'responded_at' => $now,
                        'updated_at'   => $now,
                    ]);

                if ($updated) {
                    try {
                        OfferBroadcaster::emitStatus(
                            (int)$r->tenant_id,
                            (int)$r->driver_id,
                            (int)$r->ride_id,
                            (int)$r->id,
                            'expired'
                        );
                    } catch (\Throwable $e) {
                        Log::warning('offers.expire emit failed', [
                            'tenant' => (int)$r->tenant_id,
                            'driver' => (int)$r->driver_id,
                            'ride'   => (int)$r->ride_id,
                            'offer'  => (int)$r->id,
                            'msg'    => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
})->everyMinute()
  ->name('offers.expire')
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

// Si todavía ocupas tenants:bill, deja SOLO UNO (NO por clase y por firma a la vez)
// Schedule::command('tenants:bill')->dailyAt('03:00')->name('tenants.bill')->withoutOverlapping();


// =====================================================
// 5) Purga chat (si lo reactivas, solo una vez)
// =====================================================
// Schedule::command('chat:purge-old --days=60')
//     ->dailyAt('03:00')
//     ->name('chat.purgeOld')
//     ->withoutOverlapping();
