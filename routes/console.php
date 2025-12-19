<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Admin\RideAdminController;
use Illuminate\Support\Facades\Schedule;
use App\Services\ScheduledRidesService;
use App\Services\OfferBroadcaster;
use App\Console\Commands\TenantsBill;
// Corre cada minuto
Schedule::call(function () {
    $now = now();
    $cutOffOffline = now()->subMinutes(30);
    $cutOffAuto    = now()->subMinutes(60);

    DB::table('drivers')
      ->where(function($q){ $q->whereNull('last_seen_at')->orWhere('status','!=','offline'); })
      ->where(function($q) use ($cutOffOffline){ $q->whereNull('last_seen_at')->orWhere('last_seen_at','<',$cutOffOffline); })
      ->update(['status'=>'offline','updated_at'=>$now]);

    $open = DB::table('driver_shifts as ds')
      ->join('drivers as d','d.id','=','ds.driver_id')
      ->whereNull('ds.ended_at')
      ->where(function($q) use ($cutOffAuto){ $q->whereNull('d.last_seen_at')->orWhere('d.last_seen_at','<',$cutOffAuto); })
      ->select('ds.id','d.last_seen_at')->get();

    foreach ($open as $s) {
        DB::table('driver_shifts')->where('id',$s->id)->update([
            'ended_at'=> $s->last_seen_at ?? $now, 'status'=>'cerrado', 'updated_at'=>$now
        ]);
    }
})->everyMinute();


Schedule::call(function () {
    // si tienes multi-tenant, itéralo aquí:
    $tenantIds = \DB::table('tenants')->pluck('id')->all() ?: [1];
    foreach ($tenantIds as $tenId) {
        try { ScheduledRidesService::fireDue((int)$tenId); } catch (\Throwable $e) {
            \Log::warning('scheduled fireDue fail', ['tenant'=>$tenId,'msg'=>$e->getMessage()]);
        }
    }
})
->everyMinute()
->name('rides.fireScheduled')
->withoutOverlapping();


// ===== Expirar OFERTAS vencidas y notificar al driver =====
Schedule::call(function () {
    $now = now();

    // Procesa por chunks para no cargar demasiadas filas en memoria
    DB::table('ride_offers')
        ->select('id','tenant_id','driver_id','ride_id')
        ->where('status','offered')
        ->whereNotNull('expires_at')
        ->where('expires_at','<',$now)
        ->orderBy('id')
        ->chunkById(500, function ($rows) use ($now) {
            foreach ($rows as $r) {
                // protege contra condiciones de carrera: solo cambia si aún está "offered"
                $updated = DB::table('ride_offers')
                    ->where('id', $r->id)
                    ->where('status', 'offered')
                    ->update([
                        'status'       => 'expired',
                        'responded_at' => $now,
                        'updated_at'   => $now,
                    ]);

                if ($updated) {
                    // Emite evento realtime al driver (private-tenant.{tenantId}.driver.{driverId})
                    OfferBroadcaster::emitStatus(
                        (int)$r->tenant_id,
                        (int)$r->driver_id,
                        (int)$r->ride_id,
                        (int)$r->id,
                        'expired'
                    );
                }
            }
        });
})
->everyMinute()
->name('offers.expire')
->withoutOverlapping();

// O por firma:
Schedule::command('tenants:bill')->dailyAt('03:00');

// O por clase:
Schedule::command(TenantsBill::class)->dailyAt('03:00');


  Schedule::command('tenants:billing-daily')->dailyAt('02:10');

    // Día 1: generar mes completo
    Schedule::command('tenants:bill-month-start')->monthlyOn(1, '02:20');

/*  $schedule->command('chat:purge-old --days=60')
        ->dailyAt('03:00')
        ->withoutOverlapping();*/
