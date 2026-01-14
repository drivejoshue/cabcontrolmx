<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\DispatchSetting;

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
    private static function getUnifiedSettings(int $tenantId): object
    {
        // requestedTenantId = $tenantId (lo conservamos por si luego vuelves a individual)
        // effectiveTenantId = 100 (cerebro Orbana)
        $s = DispatchSettingsService::forTenant(self::CORE_TENANT_ID);

        // (Opcional) debug sin romper contrato del objeto:
        // $s->requested_tenant_id = $tenantId;
        // $s->effective_tenant_id = self::CORE_TENANT_ID;

        return $s;
    }

    /**
     * Dispara una ola de ofertas para un ride.
     * Intenta SPs:
     *  - sp_offer_wave_v1(tenant_id, ride_id, radius_km, limit_n, expires_s)
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
): 
  array 
  { 
      $settings = self::getUnifiedSettings($tenantId);

        if ($expires === null) {
            $expires = (int)($settings->expires_s ?? 180);
        }
    // 0) Verifica ride ofertable
    $ride = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $rideId)
        ->first();
    if (!$ride) {
        return ['ok'=>false,'reason'=>'ride_not_found'];
    }
    if (in_array(strtolower($ride->status), ['accepted','en_route','arrived','on_board','finished','canceled'])) {
        return ['ok'=>false,'reason'=>'ride_not_offerable','status'=>$ride->status];
    }
    if (!is_null($ride->driver_id)) {
        return ['ok'=>false,'reason'=>'already_assigned','driver_id'=>$ride->driver_id];
    }

    // Marcas para detectar lo NUEVO de esta corrida
    $t0 = now();
    $beforeMaxId = (int)(DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('ride_id',   $rideId)
        ->max('id') ?? 0);

    // Helper para emitir todas las 'offered' creadas desde t0 / id>beforeMaxId
    $emitNewOffers = function() use ($tenantId, $rideId, $beforeMaxId, $t0) : array {
        // 1) Primero por id (más robusto y barato)
        $ids = DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id',   $rideId)
            ->where('status',    'offered')
            ->whereNull('responded_at')
            ->where('id', '>', $beforeMaxId)
            ->pluck('id')
            ->map(fn($id)=>(int)$id)
            ->all();

        // 2) Si por alguna razón el id no sirve (replicación/desfase), cae por tiempo
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

        foreach ($ids as $oid) {
            try { \App\Services\OfferBroadcaster::emitNew((int)$oid); } catch (\Throwable $e) {
                // no interrumpas — el polling corrige; deja log si quieres
                \Log::warning('kickoff emitNew fail', ['offer_id'=>$oid,'msg'=>$e->getMessage()]);
            }
        }
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

        // 🔔 EMITIR NUEVAS
        $createdIds = $emitNewOffers();

        return [
            'ok' => true,
            'via' => 'sp_offer_wave_prio_v3',
            'created_offer_ids' => $createdIds,
        ];

    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $isMissing = str_contains($msg, '1305') || str_contains($msg, 'does not exist');
        if (!$isMissing) {
            return ['ok'=>false,'via'=>'sp_offer_wave_prio_v3','error'=>$msg];
        }
    }

    // 2) Fallback manual: top N drivers cerca (idle + shift abierto + ping fresco) y SP por driver
    try {
        // latest location por driver (solo frescos 120s)
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
                // ignora duplicado/driver sin shift, etc.
            }
        }

        // ola (no directa)
        DB::table('ride_offers')
          ->where('tenant_id', $tenantId)
          ->where('ride_id',   $rideId)
          ->where('status',    'offered')
          ->whereNull('responded_at')
          ->update(['is_direct' => 0]);

        // 🔔 EMITIR NUEVAS
        $createdIds = $emitNewOffers();

        // auto-aceptar si SOLO hay 1 y así se pide
        if ($created === 1 && $autoAssignIfSingle) {
            $offerId = DB::table('ride_offers')
                ->where('tenant_id',$tenantId)
                ->where('ride_id',$rideId)
                ->orderByDesc('id')->value('id');

            if ($offerId) {
                try { DB::statement('CALL sp_accept_offer_v7(?)', [$offerId]); } catch (\Throwable $ee) {}

                // Opcional: emitir accepted/ride.active (por si quieres real-time inmediato)
                try {
                    $row = DB::table('ride_offers')->where('id',$offerId)->first(['tenant_id','driver_id','ride_id']);
                    if ($row) {
                        \App\Services\OfferBroadcaster::emitStatus((int)$row->tenant_id,(int)$row->driver_id,(int)$row->ride_id,(int)$offerId,'accepted');
                        \App\Services\Realtime::toDriver((int)$row->tenant_id,(int)$row->driver_id)->emit('ride.active', [
                            'ride_id'  => (int)$row->ride_id,
                            'offer_id' => (int)$offerId,
                        ]);
                    }
                } catch (\Throwable $ee) {}

                return [
                    'ok'=>true,
                    'via'=>'fallback+auto_accept',
                    'offers_created'=>$created,
                    'created_offer_ids'=>$createdIds,
                    'offer_id'=>$offerId,
                ];
            }
        }

        return [
            'ok'=>true,
            'via'=>'fallback',
            'offers_created'=>$created,
            'created_offer_ids'=>$createdIds,
        ];

    } catch (\Throwable $e2) {
        return ['ok'=>false,'via'=>'fallback','error'=>$e2->getMessage()];
    }
}

}
