<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\DispatchSetting;

class AutoDispatchService
{
    /** Lee settings por tenant con defaults sanos */
  public static function settings(int $tenantId): object
{
    $row = \App\Models\DispatchSetting::query()
        ->where('tenant_id', $tenantId)
        ->orderByDesc('id')
        ->first();

    // Flags y nombres compatibles (null-safe)
    $enabled     = data_get($row, 'auto_dispatch_enabled',
                    data_get($row, 'auto_enabled', true));
    $delay       = data_get($row, 'auto_dispatch_delay_s',
                    data_get($row, 'auto_delay_sec', 20));
    $radius      = data_get($row, 'auto_dispatch_radius_km', 5.0);
    $limitN      = data_get($row, 'auto_dispatch_preview_n',
                    data_get($row, 'wave_size_n', 12));
    $expires     = data_get($row, 'offer_expires_sec', 180);
    $autoSingle  = data_get($row, 'auto_assign_if_single', false);

    // TTL de ping (segundos)
    $freshSec    = data_get($row, 'driver_fresh_sec',
                    data_get($row, 'loc_fresh_sec',
                    data_get($row, 'ping_max_age_sec', 120)));

    // Fare bidding: acepta singular o plural en BD
    $allowFare   = (bool) data_get($row, 'allow_fare_bidding',
                        data_get($row, 'allow_fare_biddings', false));

    return (object)[
        'enabled'                => (bool)  $enabled,
        'delay_s'                => (int)   $delay,
        'radius_km'              => (float) $radius,
        'limit_n'                => (int)   $limitN,
        'expires_s'              => (int)   $expires,
        'auto_assign_if_single'  => (bool)  $autoSingle,
        'fresh_s'                => (int)   $freshSec,

        // principal (lo que deben leer las UIs)
        'allow_fare_bidding'     => (bool)  $allowFare,

        // compatibilidad temporal (DEPRECATED)
        'allow_fare_biddings'    => (bool)  $allowFare,
    ];
}


    /**
     * Dispara una ola de ofertas para un ride.
     * Intenta SPs:
     *  - sp_offer_wave_v1(tenant_id, ride_id, radius_km, limit_n, expires_s)
     * Fallback: buscar candidatos y llamar sp_create_offer_v2 driver por driver.
     *
     * IMPORTANTE:
     * - Pre-chequea conductores frescos; si no hay, no llames al SP.
     * - Si el SP no filtra frescura, limpia ofertas hacia conductores con ping viejo.
     */
    public static function kickoff(
        int $tenantId,
        int $rideId,
        float $lat,
        float $lng,
        float $km,
        int $expires = 45,
        int $limitN = 6,
        bool $autoAssignIfSingle = false
    ): array {
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

        // Config (para TTL de ping)
        $cfg    = self::settings($tenantId);
        $freshS = max(1, (int)$cfg->fresh_s);

        // ---- helper: últimos pings por driver (en este tenant) ----
        $latest = DB::table('driver_locations as dl1')
            ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
            ->where('dl1.tenant_id', $tenantId)
            ->groupBy('dl1.driver_id');

        $locs = DB::table('driver_locations as dl')
            ->joinSub($latest,'last',function($j){
                $j->on('dl.driver_id','=','last.driver_id')->on('dl.id','=','last.last_id');
            })
            ->where('dl.tenant_id', $tenantId)
            ->select('dl.driver_id','dl.lat','dl.lng','dl.reported_at');

        // PRE-CHECK: ¿hay al menos 1 driver fresco dentro del radio?
        $pre = DB::table('drivers as d')
            ->join('driver_shifts as s', function($j){
                $j->on('s.driver_id','=','d.id')->whereNull('s.ended_at');
            })
            ->leftJoinSub($locs,'loc',function($j){
                $j->on('loc.driver_id','=','d.id');
            })
            ->where('d.tenant_id', $tenantId)
            ->where('d.status', 'idle')
            ->whereNotNull('loc.lat')
            ->where('loc.reported_at','>=', now()->subSeconds($freshS))
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
            ->pluck('driver_id');

        if ($pre->isEmpty()) {
            // No hay candidatos frescos → NO llamar SP, ni fallback.
            return ['ok'=>false,'reason'=>'no_fresh_candidates','fresh_s'=>$freshS];
        }

        // 1) Try: SP OLA v1
        try {
            DB::statement('CALL sp_offer_wave_v1(?, ?, ?, ?, ?)', [
                $tenantId, $rideId, $km, $limitN, $expires
            ]);

            // Marca como no-directas
            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id',   $rideId)
                ->where('status',    'offered')
                ->whereNull('responded_at')
                ->update(['is_direct' => 0]);

            // LIMPIEZA post-SP: libera ofertas para pings viejos (o sin ping)
            $staleIds = DB::table('ride_offers as ro')
                ->leftJoinSub($locs, 'loc', function($j){
                    $j->on('loc.driver_id', '=', 'ro.driver_id');
                })
                ->where('ro.tenant_id', $tenantId)
                ->where('ro.ride_id',   $rideId)
                ->where('ro.status',    'offered')
                ->where(function($q) use ($freshS){
                    $q->whereNull('loc.reported_at')
                      ->orWhere('loc.reported_at','<', now()->subSeconds($freshS));
                })
                ->pluck('ro.id');

            if ($staleIds->count() > 0) {
                DB::table('ride_offers')
                    ->whereIn('id', $staleIds)
                    ->update([
                        'status'       => 'released',
                        'responded_at' => now(),
                        'updated_at'   => now(),
                    ]);
            }

            // Si todas quedaron “released”, considera no_fresh
            $left = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id',   $rideId)
                ->where('status',    'offered')
                ->count();

            if ($left === 0) {
                return [
                    'ok'      => false,
                    'via'     => 'sp_offer_wave_v1',
                    'reason'  => 'all_offers_stale_released',
                    'fresh_s' => $freshS
                ];
            }

            return [
                'ok'                    => true,
                'via'                   => 'sp_offer_wave_v1',
                'released_stale_offers' => $staleIds->count(),
                'fresh_s'               => $freshS
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isMissing = str_contains($msg, '1305') || str_contains($msg, 'does not exist');
            if (!$isMissing) {
                return ['ok'=>false,'via'=>'sp_offer_wave_v1','error'=>$msg];
            }
        }

        // 2) Fallback manual: top N drivers cerca (idle + shift abierto + ping fresco) y SP por driver
        try {
            $candidates = DB::table('drivers as d')
                ->join('driver_shifts as s', function($j){
                    $j->on('s.driver_id','=','d.id')->whereNull('s.ended_at');
                })
                ->leftJoinSub($locs,'loc',function($j){
                    $j->on('loc.driver_id','=','d.id');
                })
                ->where('d.tenant_id', $tenantId)
                ->where('d.status', 'idle')
                ->whereNotNull('loc.lat')
                ->where('loc.reported_at','>=', now()->subSeconds($freshS))
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

            if ($candidates->isEmpty()) {
                return ['ok'=>false,'via'=>'fallback','reason'=>'no_fresh_candidates','fresh_s'=>$freshS];
            }

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

            DB::table('ride_offers')
              ->where('tenant_id', $tenantId)
              ->where('ride_id',   $rideId)
              ->where('status',    'offered')
              ->whereNull('responded_at')
              ->update(['is_direct' => 0]);

            // auto-aceptar si SOLO hay 1 y así se pide
            if ($created === 1 && $autoAssignIfSingle) {
                $offerId = DB::table('ride_offers')
                    ->where('tenant_id',$tenantId)
                    ->where('ride_id',$rideId)
                    ->orderByDesc('id')->value('id');
                if ($offerId) {
                    try { DB::statement('CALL sp_accept_offer_v3(?)', [$offerId]); } catch (\Throwable $ee) {}
                    return ['ok'=>true,'via'=>'fallback+auto_accept','offers_created'=>$created,'offer_id'=>$offerId];
                }
            }

            return ['ok'=>true,'via'=>'fallback','offers_created'=>$created,'fresh_s'=>$freshS];
        } catch (\Throwable $e2) {
            return ['ok'=>false,'via'=>'fallback','error'=>$e2->getMessage()];
        }
    }
}
