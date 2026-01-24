<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\DriverEvent;
use Carbon\Carbon;

class OfferBroadcaster
{
  public static function emitNew(int $offerId): void
{
    // ================================================
    // 🔒 PROTECCIÓN CONTRA DUPLICADOS
    // ================================================
    
    // 1) Verificar si ya fue enviada (sent_at)
    $alreadySent = DB::table('ride_offers')
        ->where('id', $offerId)
        ->whereNotNull('sent_at')
        ->exists();
    
    if ($alreadySent) {
        \Log::warning('OfferBroadcaster::emitNew - Already sent, skipping', [
            'offer_id' => $offerId,
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
        ]);
        return;
    }
    
    // 2) Verificar si ya está en outbox (pendiente de procesar)
    $inOutbox = DB::table('dispatch_outbox')
        ->where('offer_id', $offerId)
        ->where('topic', 'offer.new')
        ->whereIn('status', ['PENDING', 'PROCESSING', 'FAILED'])
        ->exists();
    
    if ($inOutbox) {
        \Log::warning('OfferBroadcaster::emitNew - Already in outbox, skipping', [
            'offer_id' => $offerId,
        ]);
        return;
    }
    
    // 3) Marcar como enviada INMEDIATAMENTE (para prevenir duplicados)
    DB::table('ride_offers')
        ->where('id', $offerId)
        ->update(['sent_at' => Carbon::now()]);
    
    // ================================================
    // 📦 CÓDIGO ORIGINAL (con manejo de errores)
    // ================================================
    try {
        $o = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->join('drivers as d', 'd.id', '=', 'o.driver_id')
            ->where('o.id', $offerId)
            ->select([
                'o.id as offer_id',
                'o.tenant_id',
                'o.driver_id',
                'o.ride_id',
                'o.is_direct',
                'o.round_no',
                'o.expires_at',
                'o.eta_seconds',
                'o.distance_m',

                'r.requested_channel',
                'r.origin_lat',
                'r.origin_lng',
                'r.origin_label',
                'r.dest_lat',
                'r.dest_lng',
                'r.dest_label',
                'r.pax',
                'r.notes',
                'r.quoted_amount',
                'r.distance_m as route_distance_m',
                'r.duration_s as route_duration_s',
                'r.route_polyline',
                'r.stops_json',

                // campos de bidding / marketplace
                'r.passenger_offer',
                'r.agreed_amount',

                'd.status as driver_status',
            ])
            ->first();

        if (!$o) {
            \Log::warning('OfferBroadcaster::emitNew - Offer no encontrada', [
                'offerId' => $offerId
            ]);
            
            // Si no existe, desmarcar sent_at
            DB::table('ride_offers')
                ->where('id', $offerId)
                ->update(['sent_at' => null]);
            return;
        }

        $tenantId = (int) $o->tenant_id;
        $driverId = (int) $o->driver_id;
        $isPassengerChannel = ($o->requested_channel === 'passenger_app');

        // Settings de Dispatch
        try {
            $s = \App\Services\DispatchSettingsService::forTenant($tenantId);
            $offerExpiresSec   = $s->expires_s;
            $allowFareBidding  = $s->allow_fare_bidding;
        } catch (\Exception $e) {
            \Log::error('OfferBroadcaster::emitNew - Error obteniendo settings', [
                'error' => $e->getMessage()
            ]);
            $offerExpiresSec  = 300;
            $allowFareBidding = false;
        }

        // expires_at
        $expiresAt = $o->expires_at;
        if (empty($expiresAt)) {
            $expiresAt = Carbon::now()
                ->addSeconds($offerExpiresSec)
                ->format('Y-m-d H:i:s');

            // ✅ Persistir para que el expirador funcione
            DB::table('ride_offers')
                ->where('id', $offerId)
                ->whereNull('expires_at')
                ->update([
                    'expires_at'  => $expiresAt,
                    'updated_at'  => Carbon::now(),
                ]);

            \Log::info('OfferBroadcaster::emitNew - expires_at calculado y guardado', [
                'offerId'   => $offerId,
                'expiresAt' => $expiresAt
            ]);
        }

        // Preview
        $etaSeconds = $o->eta_seconds ?? 60;
        $distanceM  = $o->distance_m ?? 1000;

        // Flags UI
        $biddingAllowed = $allowFareBidding && $isPassengerChannel;
        $popupAllowed   = ((int) $o->is_direct === 1)
            || (
                (int) $o->is_direct === 0
                && strtolower((string) $o->driver_status) === 'idle'
            );

        // Stops
        $stops = [];
        if (!empty($o->stops_json)) {
            $tmp = json_decode($o->stops_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $stops = $tmp;
            }
        }

        // === Normalizar montos ===
        $quotedAmount    = $o->quoted_amount   !== null ? (float) $o->quoted_amount    : null;
        $passengerOffer  = $o->passenger_offer !== null ? (float) $o->passenger_offer  : null;
        $agreedAmount    = $o->agreed_amount   !== null ? (float) $o->agreed_amount    : null;

        // Monto principal para el driver:
        $mainAmount = $agreedAmount
            ?? $passengerOffer
            ?? $quotedAmount;

        // Base para bidding
        $biddingBase = $passengerOffer ?? $quotedAmount;

        // Payload final
        $payload = [
            'ride_id'          => (int) $o->ride_id,
            'offer_id'         => (int) $o->offer_id,
            'is_direct'        => (int) $o->is_direct,
            'requested_channel'=> (string) $o->requested_channel,
            'round_no'         => (int) ($o->round_no ?? 0),

            'expires_at'       => $expiresAt,
            'countdown_sec'    => $offerExpiresSec,

            'quoted_amount'     => $quotedAmount,
            'passenger_offer'   => $passengerOffer,
            'agreed_amount'     => $agreedAmount,
            'amount'            => $mainAmount,
            'bidding_base'      => $biddingBase,

            'bidding_allowed'   => $biddingAllowed,
                       

            'origin' => [
                'lat'   => (float) $o->origin_lat,
                'lng'   => (float) $o->origin_lng,
                'label' => $o->origin_label ?? 'Origen',
            ],
            'dest' => [
                'lat'   => $o->dest_lat !== null ? (float) $o->dest_lat : null,
                'lng'   => $o->dest_lng !== null ? (float) $o->dest_lng : null,
                'label' => $o->dest_label ?? 'Destino',
            ],
            'stops' => $stops,

            'pax'   => $o->pax ? (int) $o->pax : 1,
            'notes' => $o->notes,

            'preview' => [
                'eta_to_origin_s'  => (int) $etaSeconds,
                'dist_to_origin_m' => (int) $distanceM,
                'route_distance_m' => $o->route_distance_m !== null
                    ? (int) $o->route_distance_m
                    : (int) $distanceM,
                'route_duration_s' => $o->route_duration_s !== null
                    ? (int) $o->route_duration_s
                    : (int) $etaSeconds,
                'polyline'         => $o->route_polyline,
            ],

            'ui' => [
                'popup_allowed' => $popupAllowed,
                'show_bidding'  => $biddingAllowed && $isPassengerChannel,
            ],
        ];

        \Log::info('OfferBroadcaster::emitNew - Enviando payload', [
            'offer_id'  => $offerId,
            'tenant_id' => $tenantId,
            'driver_id' => $driverId,
            'expires_at'=> $expiresAt,
            'quoted_amount' => $quotedAmount,
        ]);

        // 🔔 Broadcast al driver
        $event = new DriverEvent($tenantId, $driverId, 'offers.new', $payload);
        broadcast($event);

    } catch (\Exception $e) {
        // Si hay error, desmarcar sent_at para permitir reintento
        DB::table('ride_offers')
            ->where('id', $offerId)
            ->update(['sent_at' => null]);
            
        \Log::error('OfferBroadcaster::emitNew - Error', [
            'offerId' => $offerId,
            'error'   => $e->getMessage(),
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

     public static function cancelOffer(int $offerId, ?string $reason = null): void
    {
        try {
            $o = DB::table('ride_offers')->where('id', $offerId)->first();
            if (!$o) {
                \Log::warning('OfferBroadcaster::cancelOffer - offer no encontrada', [
                    'offer_id' => $offerId,
                ]);
                return;
            }

            // Solo cancelar si todavía está viva
            if (!in_array($o->status, ['offered', 'queued', 'pending_passenger'], true)) {
                \Log::info('OfferBroadcaster::cancelOffer - offer no viva, se ignora', [
                    'offer_id' => $offerId,
                    'status'   => $o->status,
                ]);
                return;
            }

            DB::table('ride_offers')
                ->where('id', $offerId)
                ->update([
                    'status'       => 'canceled',
                    'responded_at' => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                    // si tienes campo cancel_reason, úsalo; si no, quítalo
                    //'cancel_reason'=> $reason,
                ]);

            // Emitir actualización al driver
            self::emitStatus(
                (int) $o->tenant_id,
                (int) $o->driver_id,
                (int) $o->ride_id,
                (int) $o->id,
                'canceled'
            );

            // Si usas cola visual, la quitamos también
            self::queueRemove((int) $o->tenant_id, (int) $o->driver_id, (int) $o->ride_id);
        } catch (\Throwable $e) {
            \Log::error('OfferBroadcaster::cancelOffer - Error', [
                'offer_id' => $offerId,
                'error'    => $e->getMessage(),
            ]);
        }
    }


        /**
     * Marcar que el driver está viendo / dejó de ver el detalle de una oferta.
     *
     * POST /api/offers/{offer}/viewing
     * body: { "status": "start" | "stop" }
     */
    public function viewing(int $offerId, Request $req)
    {
        try {
            $user = $req->user();
            if (!$user) {
                return response()->json(['ok' => false, 'msg' => 'No auth'], 401);
            }

            // Driver actual
            $driverId = DB::table('drivers')
                ->where('user_id', $user->id)
                ->value('id');

            if (!$driverId) {
                return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
            }

            // status: start / stop (por default start)
            $data = $req->validate([
                'status' => 'nullable|string|in:start,stop',
            ]);
            $status = $data['status'] ?? 'start';

            // Buscar la oferta y su ride, validando que sea de este driver
            $row = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->join('drivers as d', 'd.id', '=', 'o.driver_id')
                ->where('o.id', $offerId)
                ->where('o.driver_id', $driverId)
                ->select([
                    'o.id          as offer_id',
                    'o.tenant_id   as tenant_id',
                    'o.ride_id     as ride_id',
                    'o.eta_seconds as eta_seconds',
                    'o.distance_m  as distance_m',

                    'r.requested_channel',

                    'd.id          as driver_id',
                    'd.name        as driver_name',
                    'd.avatar_url  as avatar_url',
                    // si quieres más adelante: 'd.vehicle_label', etc.
                ])
                ->first();

            if (!$row) {
                return response()->json(['ok' => false, 'msg' => 'Offer not found'], 404);
            }

            $tenantId = (int) $row->tenant_id;
            $rideId   = (int) $row->ride_id;

            // Solo tiene sentido mandar este evento si el ride viene de passenger_app
            if (($row->requested_channel ?? null) !== 'passenger_app') {
                \Log::info('OfferController@viewing skip: not passenger_app', [
                    'offer_id' => $offerId,
                    'ride_id'  => $rideId,
                    'channel'  => $row->requested_channel,
                ]);

                // Respondemos ok pero sin emitir nada
                return response()->json([
                    'ok'      => true,
                    'skipped' => true,
                    'reason'  => 'not_passenger_app',
                ]);
            }

            // Payload de driver para el pasajero
            $driverData = [
                'name'          => $row->driver_name,
                'avatar_url'    => $row->avatar_url,
                'vehicle_label' => null, // si después quieres, lo llenamos con join a vehicles
                'vehicle_plate' => null,
                'eta_seconds'   => $row->eta_seconds !== null ? (int) $row->eta_seconds : null,
                'distance_m'    => $row->distance_m !== null ? (int) $row->distance_m : null,
            ];

            // Emitimos evento a Passenger / panel
            RideBroadcaster::offerViewing(
                tenantId:  $tenantId,
                rideId:    $rideId,
                offerId:   (int) $row->offer_id,
                driverId:  (int) $row->driver_id,
                status:    $status,
                driver:    $driverData,
            );

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            \Log::error('OfferController@viewing error', [
                'offer_id' => $offerId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'Error interno del servidor',
            ], 500);
        }
    }

}