<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class OfferBroadcaster
{
    /** Emite offers.new a partir del ID de la offer recién creada */
    public static function emitNew(int $offerId): void
    {
        $o = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->join('drivers as d', 'd.id', '=', 'o.driver_id')
            ->where('o.id', $offerId)
            ->select([
                'o.id as offer_id','o.tenant_id','o.driver_id','o.ride_id','o.is_direct','o.round_no',
                'o.expires_at','o.eta_seconds','o.distance_m',
                'r.requested_channel','r.origin_lat','r.origin_lng','r.origin_label',
                'r.dest_lat','r.dest_lng','r.dest_label',
                'r.pax','r.notes','r.quoted_amount',
                'r.distance_m as route_distance_m','r.duration_s as route_duration_s','r.route_polyline',
                'r.stops_json',
                'd.status as driver_status',
            ])->first();

        if (!$o) return;

        $tenantId = (int)$o->tenant_id;
        $driverId = (int)$o->driver_id;

        // settings
        $s = \App\Services\AutoDispatchService::settings($tenantId);

        // UI flags
        $biddingAllowed = (bool)$s->allow_fare_bidding && ($o->requested_channel === 'passenger_app');
        $popupAllowed   = ((int)$o->is_direct === 1) || (
            (int)$o->is_direct === 0 && strtolower((string)$o->driver_status) === 'idle'
        );

        // stops decodificados
        $stops = [];
        if (!empty($o->stops_json)) {
            $tmp = json_decode($o->stops_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $stops = $tmp;
        }

        Realtime::toDriver($tenantId, $driverId)->emit('offers.new', [
            'ride_id'           => (int)$o->ride_id,
            'offer_id'          => (int)$o->offer_id,
            'is_direct'         => (int)$o->is_direct,
            'requested_channel' => (string)$o->requested_channel,
            'round_no'          => (int)($o->round_no ?? 0),
            'expires_at'        => optional($o->expires_at)?->format('Y-m-d H:i:s'),
            'countdown_sec'     => $s->expires_s ?? null,

            'quote_amount'      => $o->quoted_amount !== null ? (float)$o->quoted_amount : null,
            'bidding_allowed'   => $biddingAllowed,

            'origin' => ['lat'=>(float)$o->origin_lat,'lng'=>(float)$o->origin_lng,'label'=>$o->origin_label],
            'dest'   => ['lat'=>$o->dest_lat !== null?(float)$o->dest_lat:null,
                         'lng'=>$o->dest_lng !== null?(float)$o->dest_lng:null,
                         'label'=>$o->dest_label],
            'stops'  => $stops,

            'pax'    => $o->pax ? (int)$o->pax : null,
            'notes'  => $o->notes,

            'preview' => [
                'eta_to_origin_s' => $o->eta_seconds !== null ? (int)$o->eta_seconds : null,
                'dist_to_origin_m'=> $o->distance_m  !== null ? (int)$o->distance_m  : null,
                'route_distance_m'=> $o->route_distance_m !== null ? (int)$o->route_distance_m : null,
                'route_duration_s'=> $o->route_duration_s !== null ? (int)$o->route_duration_s : null,
                'polyline'        => $o->route_polyline,
            ],

            'ui' => [
                'popup_allowed' => $popupAllowed,
                'show_bidding'  => $biddingAllowed && ($o->requested_channel === 'passenger_app'),
            ],
        ]);
    }

    /** Emite offers.update {status} */
    public static function emitStatus(int $tenantId, int $driverId, int $rideId, int $offerId, string $status): void
    {
        Realtime::toDriver($tenantId, $driverId)->emit('offers.update', [
            'ride_id' => $rideId, 'offer_id' => $offerId, 'status' => $status
        ]);
    }

    /** Utilidades de cola */
    public static function queueAdd(int $tenantId, int $driverId, int $rideId): void {
        Realtime::toDriver($tenantId, $driverId)->emit('queue.add', ['ride_id'=>$rideId]);
    }
    public static function queueRemove(int $tenantId, int $driverId, int $rideId): void {
        Realtime::toDriver($tenantId, $driverId)->emit('queue.remove', ['ride_id'=>$rideId]);
    }
    public static function queueClear(int $tenantId, int $driverId): void {
        Realtime::toDriver($tenantId, $driverId)->emit('queue.clear', []);
    }
}
