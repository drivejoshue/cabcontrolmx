<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoKickService
{
    /**
     * Cuando un driver queda IDLE, intenta "despertar" la demanda:
     * - Busca el ride más cercano SIN driver y SIN offers vivas.
     * - Si el ride es Passenger App => dispara WAVE (kickoff).
     * - Si es Central/Dispatch => oferta directa a ese driver (sp_create_offer_v2).
     */
    public static function kickNearestRideForDriver(int $tenantId, int $driverId, float $lat, float $lng): array
    {
        $cfg     = \App\Services\DispatchSettingsService::forTenant($tenantId);
        $km      = (float)($cfg->radius_km ?? 3.0);
        $expires = (int)  ($cfg->expires_s ?? 180);
        $limitN  = (int)  ($cfg->limit_n ?? 6);

        // 0) Validación: shift abierto
        $hasShift = DB::table('driver_shifts')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereNull('ended_at')
            ->exists();

        if (!$hasShift) {
            Log::info('AutoKick.skip', [
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'skipped'   => 'no_open_shift',
            ]);
            return ['ok' => true, 'via' => 'autokick', 'skipped' => 'no_open_shift'];
        }

        // 1) Buscar ride candidato más cercano (SIN offers vivas)
        //    OJO: usa tu canon real: requested/queued (no "offered" como status del ride).
        $ride = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.driver_id')
            ->whereIn('r.status', ['offered', 'queued']) // ✅ canon típico
            ->whereNotExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('ride_offers as o')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->where('o.tenant_id', $tenantId)
                    ->whereIn('o.status', ['offered', 'pending_passenger', 'accepted']);
            })
            ->select([
                'r.id',
                'r.origin_lat',
                'r.origin_lng',
                'r.status',
                'r.requested_channel',
                DB::raw(sprintf("
                    (6371 * acos(
                        cos(radians(%F)) * cos(radians(r.origin_lat)) * cos(radians(r.origin_lng) - radians(%F))
                        + sin(radians(%F)) * sin(radians(r.origin_lat))
                    )) as dist_km
                ", $lat, $lng, $lat)),
            ])
            ->having('dist_km', '<=', $km)
            ->orderBy('dist_km')
            ->first();

        if (!$ride) {
            Log::info('AutoKick.skip', [
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'skipped'   => 'no_candidate_ride',
                'km'        => $km,
            ]);
            return ['ok' => true, 'via' => 'autokick', 'skipped' => 'no_candidate_ride'];
        }

        $rideId = (int)$ride->id;
        $channel = strtolower((string)($ride->requested_channel ?? ''));

        Log::info('AutoKick.pick', [
            'tenant_id' => $tenantId,
            'driver_id' => $driverId,
            'ride_id'   => $rideId,
            'channel'   => $channel,
            'dist_km'   => $ride->dist_km ?? null,
            'ride_status' => $ride->status ?? null,
        ]);

        // 2) PASAJERO APP => disparar WAVE (NO direct offer)
        if ($channel === 'passenger_app') {
            try {
                $res = \App\Services\AutoDispatchService::kickoff(
                    tenantId: $tenantId,
                    rideId:   $rideId,
                    lat:      (float)$ride->origin_lat,
                    lng:      (float)$ride->origin_lng,
                    km:       $km,
                    expires:  $expires,
                    limitN:   $limitN,
                    autoAssignIfSingle: false
                );

                Log::info('AutoKick.wave_done', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $rideId,
                    'driver_id' => $driverId,
                    'res'       => $res,
                ]);

                return [
                    'ok'      => true,
                    'via'     => 'autokick_wave',
                    'ride_id' => $rideId,
                    'res'     => $res,
                ];
            } catch (\Throwable $e) {
                Log::error('AutoKick.wave_fail', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $rideId,
                    'driver_id' => $driverId,
                    'err'       => $e->getMessage(),
                ]);
                return ['ok' => false, 'via' => 'autokick_wave', 'error' => $e->getMessage()];
            }
        }

        // 3) DISPATCH/CENTRAL => oferta directa SOLO a este driver
        try {
            DB::statement('CALL sp_create_offer_v2(?, ?, ?, ?)', [
                $tenantId,
                $rideId,
                $driverId,
                $expires,
            ]);

            $offerId = (int) DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $rideId)
                ->where('driver_id', $driverId)
                ->orderByDesc('id')
                ->value('id');

            if ($offerId) {
                DB::table('ride_offers')->where('id', $offerId)->update(['is_direct' => 1]);

                try {
                    \App\Services\OfferBroadcaster::emitNew($offerId);
                } catch (\Throwable $e) {
                    Log::warning('AutoKick.emitNew.fail', [
                        'offer_id' => $offerId,
                        'err'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info('AutoKick.direct_offer_created', [
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'ride_id'   => $rideId,
                'offer_id'  => $offerId,
                'dist_km'   => $ride->dist_km ?? null,
            ]);

            return [
                'ok'      => true,
                'via'     => 'autokick_direct',
                'ride_id' => $rideId,
                'offer_id'=> $offerId,
                'dist_km' => $ride->dist_km ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('AutoKick.direct_offer_fail', [
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'ride_id'   => $rideId,
                'err'       => $e->getMessage(),
            ]);
            return ['ok' => false, 'via' => 'autokick_direct', 'error' => $e->getMessage()];
        }
    }
}
