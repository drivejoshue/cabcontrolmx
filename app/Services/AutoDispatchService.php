<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\DispatchSetting;

class AutoDispatchService
{
    /** Lee settings por tenant con defaults sanos */
    public static function settings(int $tenantId): object
    {
        $row = DispatchSetting::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        // NOTA: usa nombres reales; dejo doble fallback por si existen ambos nombres en tu DB
        $delay = $row->auto_dispatch_delay_s ?? $row->auto_delay_sec ?? 20;
        $radius = $row->auto_dispatch_radius_km ?? 5.0;
        $limitN = $row->auto_dispatch_preview_n ?? $row->wave_size_n ?? 12;
        $enabled = $row->auto_enabled ?? true;
        $expires = $row->offer_expires_sec ?? 180;
         $autoSingle  = data_get($row, 'auto_assign_if_single', false);
         $allowFare   = (bool) data_get($row, 'allow_fare_bidding',
                        data_get($row, 'allow_fare_biddings', false));

        return (object)[
            'enabled'               => (bool)  $enabled,
            'delay_s'               => (int)   $delay,
            'radius_km'             => (float) $radius,
            'limit_n'               => (int)   $limitN,
            'expires_s'             => (int)   $expires,
            'auto_assign_if_single' => (bool)  $autoSingle,
            'allow_fare_bidding'     => (bool)  $allowFare,
            'auto_assign_if_single'  => (bool)  $autoSingle,

     

        ];
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

        // 1) Try: SP OLA v1
        try {
            // Si la tienes instalada, esto crea N ofertas válidas evitando duplicados
            DB::statement('CALL sp_offer_wave_v1(?, ?, ?, ?, ?)', [
                $tenantId, $rideId, $km, $limitN, $expires
            ]);

             DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id',   $rideId)
            ->where('status',    'offered')
            ->whereNull('responded_at')
            ->update(['is_direct' => 0]);


            return ['ok'=>true,'via'=>'sp_offer_wave_v1'];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isMissing = str_contains($msg, '1305') || str_contains($msg, 'does not exist');
            if (!$isMissing) {
                // otro error SQL real
                return ['ok'=>false,'via'=>'sp_offer_wave_v1','error'=>$msg];
            }
        }

        // 2) Fallback manual: top N drivers cerca (idle + shift abierto + ping fresco) y SP por driver
        //   — Si tienes sp_nearby_drivers: úsalo; si no, usa el SELECT directo.
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
                ->where('d.status', 'idle')
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
            DB::table('ride_offers')
              ->where('tenant_id', $tenantId)
              ->where('ride_id',   $rideId)
              ->where('status',    'offered')
              ->whereNull('responded_at')
              ->update(['is_direct' => 0]);


            // auto-aceptar si SOLO hay 1 y así se pide
            if ($created === 1 && $autoAssignIfSingle) {
                // recupera la offer recien creada
                $offerId = DB::table('ride_offers')
                    ->where('tenant_id',$tenantId)
                    ->where('ride_id',$rideId)
                    ->orderByDesc('id')->value('id');
                if ($offerId) {
                    try { DB::statement('CALL sp_accept_offer_v3(?)', [$offerId]); } catch (\Throwable $ee) {}
                    return ['ok'=>true,'via'=>'fallback+auto_accept','offers_created'=>$created,'offer_id'=>$offerId];
                }
            }

            return ['ok'=>true,'via'=>'fallback','offers_created'=>$created];
        } catch (\Throwable $e2) {
            return ['ok'=>false,'via'=>'fallback','error'=>$e2->getMessage()];
        }
    }
}
