<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\DispatchSetting;
use App\Services\DispatchOutbox;

class AutoDispatchService
{   
    private const CORE_TENANT_ID = 100;
    
    /** Lee settings por tenant con defaults sanos */
    public static function settings(int $tenantId): object
    {
        // ✅ REDIRIGIR al servicio unificado
        return self::getUnifiedSettings($tenantId);
    }

    /** 
     * Método privado para obtener settings unificados
     */
     /** Settings unificados: tenant 100 manda */
   /** Settings unificados: tenant actual manda; fallback a 100 lo resuelve DispatchSettingsService */
    private static function getUnifiedSettings(int $tenantId): object
    {
        // ✅ ahora lee el tenant actual (1, 101, etc.)
        // y DispatchSettingsService se encarga del fallback a 100 solo si un campo viene vacío.
        return DispatchSettingsService::forTenant($tenantId);
    }

    /**
     * Dispara una ola de ofertas para un ride.
     * Intenta SPs:
     *  - sp_offer_wave_prio_v3(tenant_id, ride_id, radius_km, limit_n, expires_s)
     * Fallback: buscar candidatos y llamar sp_create_offer_v2 driver por driver.
     */
 public static function kickoff(
    int $tenantId,
    int $rideId,
    float $lat,
    float $lng,
    float $km,
    int $expires = null,
    int $limitN = 6,
    bool $autoAssignIfSingle = false
): array {

    $settings = self::getUnifiedSettings($tenantId);
    if ($expires === null) {
        $expires = (int)($settings->expires_s ?? 180);
    }

    $now = now();

    // 0) Verifica ride ofertable
    $ride = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $rideId)
        ->first();

    if (!$ride) {
        return ['ok' => false, 'reason' => 'ride_not_found'];
    }

    if (in_array(strtolower((string)$ride->status), ['accepted','en_route','arrived','on_board','finished','canceled'], true)) {
        return ['ok' => false, 'reason' => 'ride_not_offerable', 'status' => $ride->status];
    }

    if (!is_null($ride->driver_id)) {
        return ['ok' => false, 'reason' => 'already_assigned', 'driver_id' => $ride->driver_id];
    }

    // ============================================================
    // 0.25) LIMITAR A 5 OLAS POR RIDE (sin migraciones)
    // Usamos ride_status_history como bitácora "wave_kickoff"
    // ============================================================
    $waveCount = (int) DB::table('ride_status_history')
        ->where('tenant_id', $tenantId)
        ->where('ride_id', $rideId)
        ->where('new_status', 'wave_kickoff')
        ->count();

    // ============================================================
    // 0.5) Guard: si ya hay offers vivas (solo las relevantes para pasajero)
    // NOTA: 'queued' NO debe bloquear relanzar ola (no es offer viva al pasajero)
    // ============================================================
    $hasAlive = DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('ride_id', $rideId)
        ->whereIn('status', ['offered', 'pending_passenger'])
        ->where(function ($q) use ($now) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', $now);
        })
        ->exists();

    if ($hasAlive) {
        \Log::info('kickoff skip_alive_offers', [
            'tenant_id' => $tenantId,
            'ride_id' => $rideId,
            'waveCount' => $waveCount,
        ]);

        return [
            'ok' => true,
            'via' => 'skip_alive_offers',
            'reason' => 'ride_has_alive_offers',
            'waveCount' => $waveCount,
        ];
    }

    // ============================================================
    // 0.75) Si ya llegó a 6 olas y no hay offers vivas, CANCELA ride
    // ============================================================
    if ($waveCount >= 5) {
        \Log::warning('kickoff blocked: max waves reached', [
            'tenant_id' => $tenantId,
            'ride_id' => $rideId,
            'waveCount' => $waveCount,
        ]);

        $updated = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideId)
            ->whereNull('canceled_at')
            ->whereNotIn('status', ['finished','canceled'])
            ->update([
                'status'        => 'canceled',
                'canceled_at'   => $now,
                'canceled_by'   => 'system',
                'cancel_reason' => 'Límite de olas alcanzado',
                'updated_at'    => $now,
            ]);

        if ($updated) {
            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $rideId,
                'prev_status' => (string)($ride->status ?? 'offered'),
                'new_status'  => 'canceled',
                'meta'        => json_encode(['by'=>'system','reason'=>'max_waves_reached','max'=>2]),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            try {
                \App\Services\RideBroadcaster::canceled(
                    $tenantId,
                    $rideId,
                    'system',
                    'No se encontraron conductores (límite de intentos).'
                );
            } catch (\Throwable $e) {
                \Log::warning('emit ride.canceled failed (max waves)', [
                    'tenant' => $tenantId,
                    'ride' => $rideId,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => true, 'via' => 'skip_max_waves', 'waveCount' => $waveCount];
    }

    // ============================================================
    // Registrar que vamos a disparar una nueva ola (conteo exacto)
    // ============================================================
    DB::table('ride_status_history')->insert([
        'tenant_id'   => $tenantId,
        'ride_id'     => $rideId,
        'prev_status' => (string)($ride->status ?? 'offered'),
        'new_status'  => 'wave_kickoff',
        'meta'        => json_encode([
            'km' => $km,
            'limitN' => $limitN,
            'expires' => $expires,
            'autoAssignIfSingle' => $autoAssignIfSingle ? 1 : 0,
            'wave_num' => $waveCount + 1,
        ]),
        'created_at'  => $now,
        'updated_at'  => $now,
    ]);

    // Marcas para detectar lo NUEVO de esta corrida
    $t0 = $now;
    $beforeMaxId = (int)(DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('ride_id',   $rideId)
        ->max('id') ?? 0);

    // Helper para emitir todas las 'offered' creadas desde t0 / id>beforeMaxId
    $emitNewOffers = function() use ($tenantId, $rideId, $beforeMaxId, $t0) : array {
        // 1) Por id
        $ids = DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id',   $rideId)
            ->where('status',    'offered')
            ->whereNull('responded_at')
            ->where('id', '>', $beforeMaxId)
            ->pluck('id')
            ->map(fn($id)=>(int)$id)
            ->all();

        // 2) Fallback por tiempo
        if (empty($ids)) {
            $ids = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id',   $rideId)
                ->where('status',    'offered')
                ->whereNull('responded_at')
                ->where(function($q) use ($t0){
                    $q->where('sent_at', '>=', $t0)
                      ->orWhere('created_at','>=',$t0)
                      ->orWhere('updated_at','>=',$t0);
                })
                ->orderBy('id','desc')
                ->limit(50)
                ->pluck('id')
                ->map(fn($id)=>(int)$id)
                ->all();
        }

        // 2) ENCOLAR EN OUTBOX (NO emitir directamente)
    // foreach ($ids as $oid) {
    //     try {
    //         // Encolar para procesamiento asíncrono
    //         \App\Services\DispatchOutbox::enqueueOfferNew(
    //             tenantId: $tenantId,
    //             offerId:  $oid,
    //             rideId:   $rideId,
    //             driverId: (int) DB::table('ride_offers')
    //                 ->where('id', $oid)
    //                 ->value('driver_id')
    //         );
    //     } catch (\Throwable $e) {
    //         \Log::warning('kickoff outbox.enqueue.fail', [
    //             'offer_id' => $oid,
    //             'msg' => $e->getMessage()
    //         ]);
    //     }
    // }
    
    return $ids;
};

    // 1) Try: SP OLA v3
    try {
        DB::statement('CALL sp_offer_wave_prio_v3(?, ?, ?, ?, ?)', [
            $tenantId, $rideId, $km, $limitN, $expires
        ]);

        // marca como ola (no directa)
        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id',   $rideId)
            ->where('status',    'offered')
            ->whereNull('responded_at')
            ->update(['is_direct' => 0]);

        $createdIds = $emitNewOffers();

        \Log::info('kickoff wave done', [
            'tenant_id' => $tenantId,
            'ride_id' => $rideId,
            'wave_num' => $waveCount + 1,
            'created_offer_ids_count' => count($createdIds),
        ]);

        return [
            'ok' => true,
            'via' => 'sp_offer_wave_prio_v3',
            'wave_num' => $waveCount + 1,
            'created_offer_ids' => $createdIds,
        ];

    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $isMissing = str_contains($msg, '1305') || str_contains($msg, 'does not exist');
        if (!$isMissing) {
            return ['ok' => false, 'via' => 'sp_offer_wave_prio_v3', 'error' => $msg];
        }
    }

    // 2) Fallback manual: top N drivers cerca (idle + shift abierto + ping fresco) y SP por driver
    try {
        $latest = DB::table('driver_locations as dl1')
            ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
            ->groupBy('dl1.driver_id');

        $locs = DB::table('driver_locations as dl')
            ->joinSub($latest,'last',function($j){
                $j->on('dl.driver_id','=','last.driver_id')->on('dl.id','=','last.last_id');
            })
            ->where('dl.tenant_id', $tenantId)
            ->where('dl.reported_at','>=', now()->subSeconds(120))
            ->select('dl.driver_id','dl.lat','dl.lng');

        $candidates = DB::table('drivers as d')
            ->join('driver_shifts as s', function($j){
                $j->on('s.driver_id','=','d.id')->whereNull('s.ended_at');
            })
            ->leftJoinSub($locs,'loc',function($j){
                $j->on('loc.driver_id','=','d.id');
            })
            ->where('d.tenant_id', $tenantId)
            ->whereIn('d.status', ['idle','busy','on_ride'])
            ->whereNotNull('loc.lat')
            ->select([
                'd.id as driver_id',
                DB::raw(sprintf("
                    (6371 * acos(
                        cos(radians(%f)) * cos(radians(loc.lat)) * cos(radians(loc.lng) - radians(%f))
                        + sin(radians(%f)) * sin(radians(loc.lat))
                    )) as dist_km
                ", $lat, $lng, $lat)),
            ])
            ->having('dist_km','<=',$km)
            ->orderBy('dist_km')
            ->limit($limitN)
            ->get();

        $created = 0;
        foreach ($candidates as $c) {
            try {
                DB::statement('CALL sp_create_offer_v2(?, ?, ?, ?)', [
                    $tenantId, $rideId, (int)$c->driver_id, $expires
                ]);
                $created++;
            } catch (\Throwable $ee) {
                // ignore
            }
        }

        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id',   $rideId)
            ->where('status',    'offered')
            ->whereNull('responded_at')
            ->update(['is_direct' => 0]);

        $createdIds = $emitNewOffers();

        if ($created === 1 && $autoAssignIfSingle) {
            $offerId = DB::table('ride_offers')
                ->where('tenant_id',$tenantId)
                ->where('ride_id',$rideId)
                ->orderByDesc('id')
                ->value('id');

            if ($offerId) {
                try { DB::statement('CALL sp_accept_offer_v7(?)', [$offerId]); } catch (\Throwable $ee) {}

                try {
                    $row = DB::table('ride_offers')->where('id',$offerId)->first(['tenant_id','driver_id','ride_id']);
                    if ($row) {
                        \App\Services\OfferBroadcaster::emitStatus(
                            (int)$row->tenant_id,
                            (int)$row->driver_id,
                            (int)$row->ride_id,
                            (int)$offerId,
                            'accepted'
                        );
                        \App\Services\Realtime::toDriver((int)$row->tenant_id,(int)$row->driver_id)
                            ->emit('ride.active', [
                                'ride_id'  => (int)$row->ride_id,
                                'offer_id' => (int)$offerId,
                            ]);
                    }
                } catch (\Throwable $ee) {}

                return [
                    'ok' => true,
                    'via' => 'fallback+auto_accept',
                    'wave_num' => $waveCount + 1,
                    'offers_created' => $created,
                    'created_offer_ids' => $createdIds,
                    'offer_id' => (int)$offerId,
                ];
            }
        }

        return [
            'ok' => true,
            'via' => 'fallback',
            'wave_num' => $waveCount + 1,
            'offers_created' => $created,
            'created_offer_ids' => $createdIds,
        ];

    } catch (\Throwable $e2) {
        return ['ok' => false, 'via' => 'fallback', 'error' => $e2->getMessage()];
    }
}


}
