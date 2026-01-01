<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoKickService
{
    /**
     * Cuando un driver queda IDLE, intenta asignarle el ride más cercano:
     * - Ride: requested/queued, sin driver, SIN offers vivas (offered/pending_passenger/accepted)
     * - Crea offer directa: sp_create_offer_v2
     * - Emite OfferBroadcaster::emitNew()
     */
    public static function kickNearestRideForDriver(int $tenantId, int $driverId, float $lat, float $lng): array
    {
        $cfg = \App\Services\DispatchSettingsService::forTenant($tenantId);
        $km = (float)($cfg->radius_km ?? 3.0);
        $expires = (int)($cfg->expires_s ?? 180);

        // 0) Validación: driver con shift abierto (si tu negocio lo exige)
        $hasShift = DB::table('driver_shifts')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereNull('ended_at')
            ->exists();

        if (!$hasShift) {
            Log::info('AutoKick.skip', [
                'tenant_id'=>$tenantId,'driver_id'=>$driverId,
                'skipped'=>'no_open_shift'
            ]);
            return ['ok'=>true,'via'=>'autokick','skipped'=>'no_open_shift'];
        }

        // 1) Buscar ride candidato más cercano (sin offers vivas)
        $ride = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.driver_id')
            ->whereIn('r.status', ['offered','requested']) // ajusta a tu canon
            ->whereNotExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('ride_offers as o')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->where('o.tenant_id', $tenantId)
                    ->whereIn('o.status', ['offered','pending_passenger','accepted']);
            })
            ->select([
                'r.id','r.origin_lat','r.origin_lng','r.status',
                DB::raw(sprintf("
                    (6371 * acos(
                        cos(radians(%F)) * cos(radians(r.origin_lat)) * cos(radians(r.origin_lng) - radians(%F))
                        + sin(radians(%F)) * sin(radians(r.origin_lat))
                    )) as dist_km
                ", $lat, $lng, $lat))
            ])
            ->having('dist_km', '<=', $km)
            ->orderBy('dist_km')
            ->first();

        if (!$ride) {
            Log::info('AutoKick.skip', [
                'tenant_id'=>$tenantId,'driver_id'=>$driverId,
                'skipped'=>'no_candidate_ride'
            ]);
            return ['ok'=>true,'via'=>'autokick','skipped'=>'no_candidate_ride'];
        }

        // 2) Crear offer directa SOLO a este driver
        try {
            DB::statement('CALL sp_create_offer_v2(?, ?, ?, ?)', [
                $tenantId,
                (int)$ride->id,
                $driverId,
                $expires
            ]);

            // Toma la última offer creada para ese ride+driver
            $offerId = (int) DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', (int)$ride->id)
                ->where('driver_id', $driverId)
                ->orderByDesc('id')
                ->value('id');

            // Marca como directa (opcional)
            if ($offerId) {
                DB::table('ride_offers')->where('id', $offerId)->update(['is_direct' => 1]);
                try { \App\Services\OfferBroadcaster::emitNew($offerId); } catch (\Throwable $e) {
                    Log::warning('AutoKick.emitNew.fail', ['offer_id'=>$offerId,'err'=>$e->getMessage()]);
                }
            }

            Log::info('AutoKick.offer_created', [
                'tenant_id'=>$tenantId,'driver_id'=>$driverId,
                'ride_id'=>(int)$ride->id,'offer_id'=>$offerId,
                'dist_km'=>$ride->dist_km ?? null
            ]);

            return [
                'ok'=>true,
                'via'=>'autokick',
                'ride_id'=>(int)$ride->id,
                'offer_id'=>$offerId,
                'dist_km'=>$ride->dist_km ?? null
            ];

        } catch (\Throwable $e) {
            Log::error('AutoKick.create_offer.fail', [
                'tenant_id'=>$tenantId,'driver_id'=>$driverId,'ride_id'=>(int)$ride->id,
                'err'=>$e->getMessage()
            ]);
            return ['ok'=>false,'via'=>'autokick','error'=>$e->getMessage()];
        }
    }
}
