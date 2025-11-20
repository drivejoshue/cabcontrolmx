<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\DriverEvent;
use Carbon\Carbon;

class OfferBroadcaster
{
    public static function emitNew(int $offerId): void
    {
        try {
            $o = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->join('drivers as d', 'd.id', '=', 'o.driver_id')
                ->where('o.id', $offerId)
                ->select([
                    'o.id as offer_id', 'o.tenant_id', 'o.driver_id', 'o.ride_id', 'o.is_direct', 'o.round_no',
                    'o.expires_at', 'o.eta_seconds', 'o.distance_m',
                    'r.requested_channel', 'r.origin_lat', 'r.origin_lng', 'r.origin_label',
                    'r.dest_lat', 'r.dest_lng', 'r.dest_label',
                    'r.pax', 'r.notes', 'r.quoted_amount',
                    'r.distance_m as route_distance_m', 'r.duration_s as route_duration_s', 'r.route_polyline',
                    'r.stops_json',
                     'r.passenger_offer',      // ⬅️ NUEVO
        'r.agreed_amount',  
                    'd.status as driver_status',
                ])->first();

            if (!$o) {
                \Log::warning('OfferBroadcaster::emitNew - Offer no encontrada', ['offerId' => $offerId]);
                return;
            }

            $tenantId = (int)$o->tenant_id;
            $driverId = (int)$o->driver_id;

            // Obtener settings
            try {
                $s = \App\Services\DispatchSettingsService::forTenant($tenantId);
                $offerExpiresSec = $s->expires_s;
                $allowFareBidding = $s->allow_fare_bidding;
            } catch (\Exception $e) {
                \Log::error('OfferBroadcaster::emitNew - Error obteniendo settings', ['error' => $e->getMessage()]);
                $offerExpiresSec = 300;
                $allowFareBidding = false;
            }

            // Calcular expires_at si es necesario
            $expiresAt = $o->expires_at;
            if (empty($expiresAt)) {
                $expiresAt = Carbon::now()->addSeconds($offerExpiresSec)->format('Y-m-d H:i:s');
                \Log::info('OfferBroadcaster::emitNew - expires_at calculado', [
                    'offerId' => $offerId,
                    'expiresAt' => $expiresAt
                ]);
            }

            // Asegurar datos de preview
            $etaSeconds = $o->eta_seconds ?? 60;
            $distanceM = $o->distance_m ?? 1000;

            // UI flags
            $biddingAllowed = $allowFareBidding && ($o->requested_channel === 'passenger_app');
            $popupAllowed = ((int)$o->is_direct === 1) || (
                (int)$o->is_direct === 0 && strtolower((string)$o->driver_status) === 'idle'
            );

            // stops
            $stops = [];
            if (!empty($o->stops_json)) {
                $tmp = json_decode($o->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $stops = $tmp;
                }
            }

            // Payload
            $payload = [
                'ride_id' => (int)$o->ride_id,
                'offer_id' => (int)$o->offer_id,
                'is_direct' => (int)$o->is_direct,
                'requested_channel' => (string)$o->requested_channel,
                'round_no' => (int)($o->round_no ?? 0),
                
                'expires_at' => $expiresAt,
                'countdown_sec' => $offerExpiresSec,

                'passenger_offer'  => $o->passenger_offer !== null ? (float)$o->passenger_offer : null, // ⬅️ NUEVO
                'agreed_amount'    => $o->agreed_amount !== null ? (float)$o->agreed_amount : null,     // ⬅️ NUEVO
                'bidding_allowed'  => $biddingAllowed,
                'bidding_base'     => $o->passenger_offer !== null                                    // ⬅️ NUEVO
                                       ? (float)$o->passenger_offer
                                       : (float)($o->quoted_amount ?? 0),

                'origin' => [
                    'lat' => (float)$o->origin_lat,
                    'lng' => (float)$o->origin_lng,
                    'label' => $o->origin_label ?? 'Origen'
                ],
                'dest' => [
                    'lat' => $o->dest_lat !== null ? (float)$o->dest_lat : null,
                    'lng' => $o->dest_lng !== null ? (float)$o->dest_lng : null,
                    'label' => $o->dest_label ?? 'Destino'
                ],
                'stops' => $stops,

                'pax' => $o->pax ? (int)$o->pax : 1,
                'notes' => $o->notes,

                'preview' => [
                    'eta_to_origin_s' => (int)$etaSeconds,
                    'dist_to_origin_m' => (int)$distanceM,
                    'route_distance_m' => $o->route_distance_m !== null ? (int)$o->route_distance_m : (int)$distanceM,
                    'route_duration_s' => $o->route_duration_s !== null ? (int)$o->route_duration_s : (int)$etaSeconds,
                    'polyline' => $o->route_polyline,
                ],

                'ui' => [
                    'popup_allowed' => $popupAllowed,
                    'show_bidding' => $biddingAllowed && ($o->requested_channel === 'passenger_app'),
                ],
            ];

            \Log::info('OfferBroadcaster::emitNew - Enviando payload', [
                'offer_id' => $offerId,
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'expires_at' => $expiresAt
            ]);

            // Broadcast
            $event = new DriverEvent($tenantId, $driverId, 'offers.new', $payload);
            broadcast($event);

        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::emitNew - Error', [
                'offerId' => $offerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function emitStatus(int $tenantId, int $driverId, int $rideId, int $offerId, string $status): void
    {
        try {
            broadcast(new DriverEvent($tenantId, $driverId, 'offers.update', [
                'ride_id' => $rideId,
                'offer_id' => $offerId,
                'status' => $status,
            ]));
        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::emitStatus - Error', ['error' => $e->getMessage()]);
        }
    }

    public static function queueAdd(int $tenantId, int $driverId, int $rideId): void
    {
        try {
            $event = new DriverEvent($tenantId, $driverId, 'queue.add', ['ride_id' => $rideId]);
            broadcast($event);
        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::queueAdd - Error', ['error' => $e->getMessage()]);
        }
    }
    
    public static function queueRemove(int $tenantId, int $driverId, int $rideId): void
    {
        try {
            $event = new DriverEvent($tenantId, $driverId, 'queue.remove', ['ride_id' => $rideId]);
            broadcast($event);
        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::queueRemove - Error', ['error' => $e->getMessage()]);
        }
    }
    
    public static function queueClear(int $tenantId, int $driverId): void
    {
        try {
            $event = new DriverEvent($tenantId, $driverId, 'queue.clear', []);
            broadcast($event);
        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::queueClear - Error', ['error' => $e->getMessage()]);
        }
    }
}