<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AutoDispatchService;
use App\Services\DispatchSettingsService;

class AutoDispatchTickMinute extends Command
{
    protected $signature = 'orbanamx:autodispatch-tick {--tenant= : Forzar tenant_id} {--limit=50 : Máx rides por corrida}';
    protected $description = 'Relanza waves cada minuto para rides pending/requested que estén sin driver y sin offers vivas.';

    public function handle(): int
    {
        $onlyTenant = $this->option('tenant') ? (int)$this->option('tenant') : null;
        $limit = max(1, (int)$this->option('limit'));

        $tenantsQ = DB::table('tenants')->select('id');
        if ($onlyTenant) $tenantsQ->where('id', $onlyTenant);

        $tenants = $tenantsQ->get();

        $total = 0;

        foreach ($tenants as $t) {
            $tenantId = (int)$t->id;

            // settings
            $cfg = DispatchSettingsService::forTenant($tenantId);
            if (!($cfg->enabled ?? true)) {
                continue;
            }

            // 0) ESCALADOR BASE: si hay offer viva pero ya pasó la ventana (timeout), la liberamos y relanzamos wave.
            $standWindowSec = (int)($cfg->offer_expires_sec ?? 30);
            $graceSec = 3;

            // Tomamos offers offered sin respuesta, viejas, del tenant.
            // Además, filtramos a drivers que están en taxi_stand_queue en_cola (para asegurar "es oferta de base").
            $timedOut = DB::table('ride_offers as o')
                ->join('rides as r', function($j){
                    $j->on('r.id','=','o.ride_id');
                })
                ->join('taxi_stand_queue as q', function($j) use ($tenantId){
                    $j->on('q.driver_id','=','o.driver_id')
                      ->whereColumn('q.tenant_id','o.tenant_id')
                      ->where('q.tenant_id', $tenantId)
                      ->where('q.status','en_cola');
                })
                ->where('o.tenant_id', $tenantId)
                ->where('o.status', 'offered')
                ->whereNull('o.responded_at')
                ->whereNull('r.driver_id')
                ->whereIn('r.status', ['requested','offered'])
                ->whereNotNull('o.sent_at')
                ->where('o.sent_at', '<=', now()->subSeconds($standWindowSec + $graceSec))
                ->orderBy('o.sent_at')
                ->limit($limit)
                ->get([
                    'o.id as offer_id',
                    'o.ride_id',
                    'o.driver_id',
                    'r.origin_lat',
                    'r.origin_lng',
                    'q.stand_id as stand_id', // ✅ NUEVO: para mover posición en la base correcta
                ]);

            foreach ($timedOut as $to) {
                $offerId = (int)$to->offer_id;
                $rideId  = (int)$to->ride_id;
                $dId     = (int)$to->driver_id;
                $standId = (int)$to->stand_id;

                // Update condicional para evitar race (si ya aceptó/rechazó, no toca nada)
                $updated = DB::table('ride_offers')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $offerId)
                    ->where('status', 'offered')
                    ->whereNull('responded_at')
                    ->where('sent_at','<=', now()->subSeconds($standWindowSec + $graceSec))
                    ->update([
                        'status'       => 'released',
                        'response'     => 'expired',
                        'responded_at' => now(),
                        'updated_at'   => now(),
                        'expires_at'   => now(),
                    ]);

                if (!$updated) {
                    continue;
                }

                // ✅ SOLO ESTO: mover al final SIN cambiar status (sigue en_cola) + compactar posiciones 1..N
                try {
                    self::moveEnColaToEndAndResequence($tenantId, $standId, $dId);
                } catch (\Throwable $e) {
                    Log::warning('stand_timeout.queue_move_fail', [
                        'tenant_id' => $tenantId,
                        'stand_id'  => $standId,
                        'driver_id' => $dId,
                        'offer_id'  => $offerId,
                        'err'       => $e->getMessage(),
                    ]);
                }

                // Broadcast al driver (quitarla de inbox)
                try {
                    \App\Services\OfferBroadcaster::emitStatus($tenantId, $dId, $rideId, $offerId, 'released');
                    \App\Services\OfferBroadcaster::queueRemove($tenantId, $dId, $rideId);
                } catch (\Throwable $e) {
                    \Log::warning('stand_timeout.emit.fail', ['offer_id'=>$offerId,'err'=>$e->getMessage()]);
                }

                // Relanzar wave para continuar la cola
                try {
                    AutoDispatchService::kickoff(
                        tenantId: $tenantId,
                        rideId:   $rideId,
                        lat:      (float)$to->origin_lat,
                        lng:      (float)$to->origin_lng,
                        km:       (float)($cfg->radius_km ?? 3.0),
                        expires:  (int)  ($cfg->expires_s ?? 30),
                        limitN:   (int)  ($cfg->limit_n ?? 8),
                        autoAssignIfSingle: (bool)($cfg->auto_assign_if_single ?? false)
                    );
                } catch (\Throwable $e) {
                    \Log::error('stand_timeout.kickoff_fail', ['ride_id'=>$rideId,'offer_id'=>$offerId,'err'=>$e->getMessage()]);
                }
            }

            // rides candidatos (ajusta statuses a tu canon)
            $rides = DB::table('rides as r')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('r.driver_id')
                ->whereIn('r.status', ['offered'])
                ->whereNotExists(function($q) use ($tenantId){
                    $q->select(DB::raw(1))
                      ->from('ride_offers as o')
                      ->whereColumn('o.ride_id','r.id')
                      ->where('o.tenant_id',$tenantId)
                      ->whereIn('o.status', ['offered','pending_passenger','accepted']);
                })
                ->orderByDesc('r.id')
                ->limit($limit)
                ->get(['r.id','r.origin_lat','r.origin_lng','r.status']);

            if ($rides->isEmpty()) {
                continue;
            }

            Log::info('AutoDispatchTickMinute.tenant', [
                'tenant_id' => $tenantId,
                'rides'     => $rides->count(),
                'cfg'       => [
                    'radius_km' => $cfg->radius_km ?? null,
                    'limit_n'   => $cfg->limit_n ?? null,
                    'expires_s' => $cfg->expires_s ?? null,
                ],
            ]);

            foreach ($rides as $r) {
                try {
                    $res = AutoDispatchService::kickoff(
                        tenantId: $tenantId,
                        rideId:   (int)$r->id,
                        lat:      (float)$r.origin_lat,
                        lng:      (float)$r.origin_lng,
                        km:       (float)($cfg->radius_km ?? 3.0),
                        expires:  (int)  ($cfg->expires_s ?? 180),
                        limitN:   (int)  ($cfg->limit_n ?? 8),
                        autoAssignIfSingle: (bool)($cfg->auto_assign_if_single ?? false)
                    );

                    $total++;

                    Log::info('AutoDispatchTickMinute.kickoff', [
                        'tenant_id' => $tenantId,
                        'ride_id'   => (int)$r->id,
                        'res'       => $res,
                    ]);

                } catch (\Throwable $e) {
                    Log::error('AutoDispatchTickMinute.kickoff_failed', [
                        'tenant_id' => $tenantId,
                        'ride_id'   => (int)$r->id,
                        'err'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("OK. kickoffs=$total");
        return 0;
    }

    /**
     * Mueve al final SIN cambiar status (se queda en_cola)
     * y compacta posiciones para evitar huecos (4, 6, etc.).
     */
    private static function moveEnColaToEndAndResequence(int $tenantId, int $standId, int $driverId): void
    {
        DB::transaction(function () use ($tenantId, $standId, $driverId) {

            // 1) Tomar fila del driver en esa base
            $row = DB::table('taxi_stand_queue')
                ->where('tenant_id', $tenantId)
                ->where('stand_id',  $standId)
                ->where('driver_id', $driverId)
                ->where('status', 'en_cola')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first(['id']);

            if (!$row) {
                return;
            }

            // 2) Max position actual (activos)
            $maxPos = DB::table('taxi_stand_queue')
                ->where('tenant_id', $tenantId)
                ->where('stand_id',  $standId)
                ->whereIn('status', ['en_cola','saltado'])
                ->lockForUpdate()
                ->max('position');

            $maxPos = (int)($maxPos ?? 0);

            // 3) Mandarlo al final, PERO sigue en_cola
            DB::table('taxi_stand_queue')
                ->where('id', (int)$row->id)
                ->update([
                    'position' => $maxPos + 1,
                ]);

            // 4) Compactar posiciones 1..N (sin cambiar orden lógico)
            DB::statement("
                UPDATE taxi_stand_queue q
                JOIN (
                    SELECT id,
                           ROW_NUMBER() OVER (ORDER BY position ASC, id ASC) AS rn
                    FROM taxi_stand_queue
                    WHERE tenant_id = ?
                      AND stand_id  = ?
                      AND status IN ('en_cola','saltado')
                ) x ON x.id = q.id
                SET q.position = x.rn
                WHERE q.tenant_id = ?
                  AND q.stand_id  = ?
                  AND q.status IN ('en_cola','saltado')
            ", [$tenantId, $standId, $tenantId, $standId]);

        }, 3);
    }
}
