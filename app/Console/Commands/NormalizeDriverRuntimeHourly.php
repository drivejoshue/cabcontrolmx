<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeDriverRuntimeHourly extends Command
{
    protected $signature = 'orbana:normalize-runtime
        {--tenant= : Forzar tenant_id}
        {--dry-run : No escribe, solo reporta}
        {--limit=500 : Máx drivers por corrida}';

    protected $description = 'Normaliza inconsistencias duras (sin backfill): driver on_ride sin ride vivo; offers accepted con ride terminado.';

    public function handle(): int
    {
        $onlyTenant = $this->option('tenant') ? (int)$this->option('tenant') : null;
        $dryRun = (bool)$this->option('dry-run');
        $limit = max(1, (int)$this->option('limit'));

        // Ajusta a tu CANON real (cabcontrolmx dump)
        $rideLive = ['accepted','en_route','arrived','on_board','queued'];
        $rideDone = ['finished','canceled'];

        $now = now()->toDateTimeString();

        $baseDrivers = DB::table('drivers as d')
            ->select('d.id','d.tenant_id','d.status','d.last_seen_at','d.last_ping_at')
            ->where('d.status', 'on_ride');

        if ($onlyTenant) $baseDrivers->where('d.tenant_id', $onlyTenant);

        $drivers = $baseDrivers
            ->orderBy('d.id')
            ->limit($limit)
            ->get();

        $fixDrivers = 0;
        $fixOffers = 0;

        // 1) Driver on_ride sin ride vivo => idle
        foreach ($drivers as $d) {
            $hasLiveRide = DB::table('rides')
                ->where('tenant_id', $d->tenant_id)
                ->where('driver_id', $d->id)
                ->whereIn('status', $rideLive)
                ->exists();

            if ($hasLiveRide) {
                continue;
            }

            Log::warning('NormalizeRuntime.driver_stuck_on_ride', [
                'ts' => $now,
                'tenant_id' => (int)$d->tenant_id,
                'driver_id' => (int)$d->id,
                'driver_status' => $d->status,
                'last_seen_at' => $d->last_seen_at,
                'last_ping_at' => $d->last_ping_at,
                'action' => $dryRun ? 'dry_run' : 'set_idle',
            ]);

            if (!$dryRun) {
                DB::table('drivers')
                    ->where('id', $d->id)
                    ->update([
                        'status' => 'idle',
                        // NO inventamos last_seen/ping. Solo status.
                        'updated_at' => now(),
                    ]);
            }

            $fixDrivers++;
        }

        // 2) Offers accepted apuntando a ride terminado => released
        // Nota: se corrige por contradicción dura, no por tiempo.
        $offersQ = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->select('o.id','o.tenant_id','o.ride_id','o.driver_id','o.status','r.status as ride_status')
            ->where('o.status', 'accepted')
            ->whereIn('r.status', $rideDone);

        if ($onlyTenant) $offersQ->where('o.tenant_id', $onlyTenant);

        $offers = $offersQ->orderBy('o.id')->limit(2000)->get();

        foreach ($offers as $o) {
            Log::warning('NormalizeRuntime.offer_accepted_but_ride_done', [
                'ts' => $now,
                'tenant_id' => (int)$o->tenant_id,
                'offer_id' => (int)$o->id,
                'ride_id' => (int)$o->ride_id,
                'driver_id' => (int)$o->driver_id,
                'offer_status' => $o->status,
                'ride_status' => $o->ride_status,
                'action' => $dryRun ? 'dry_run' : 'set_released',
            ]);

            if (!$dryRun) {
                // Si tienes columna response, puedes setearla; si no, quita response.
                $payload = [
                    'status' => 'accepted',
                    'updated_at' => now(),
                ];

                // Intento defensivo (si existe response)
                try {
                    $cols = DB::getSchemaBuilder()->getColumnListing('ride_offers');
                    if (in_array('response', $cols, true)) {
                        $payload['response'] = 'accepted';
                    }
                } catch (\Throwable $e) {
                    // si falla introspección, no pasa nada
                }

                DB::table('ride_offers')->where('id', $o->id)->update($payload);
            }

            $fixOffers++;
        }

        $this->info("OK normalize-runtime: drivers_fixed=$fixDrivers offers_fixed=$fixOffers dry_run=" . ($dryRun ? 'yes' : 'no'));
        return 0;
    }
}
