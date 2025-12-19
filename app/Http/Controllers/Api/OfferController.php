<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OfferBroadcaster;
use App\Services\RideBroadcaster;
use App\Services\DispatchSettingsService;
use App\Services\Realtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    // Helper para obtener timezone del tenant
    private function tenantTz($tenantId)
    {
        return 'America/Mexico_City';
    }

  



public function index(Request $req)
{
    $user = $req->user();
    if (!$user) {
        return response()->json(['ok' => false, 'msg' => 'No auth'], 401);
    }

    $driverId = DB::table('drivers')
        ->where('user_id', $user->id)
        ->value('id');

    if (!$driverId) {
        return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
    }

    // Tenant desde header o desde el usuario
    $tid = $req->header('X-Tenant-ID') ?? $user->tenant_id;
    if (!$tid) {
        return response()->json(['ok' => false, 'msg' => 'Usuario sin tenant'], 403);
    }
    $tenantId = (int) $tid;

    $status = strtolower(trim($req->query('status', '')));

    // Estados válidos en ride_offers.status
    $validOfferStatuses = [
        'offered',
        'pending_passenger',
        'accepted',
        'rejected',
        'expired',
        'canceled',
        'released',
        'queued',
    ];

    // Ofertas que consideramos "vivas" para el driver
    $aliveOfferStatuses = ['offered', 'pending_passenger', 'queued'];

    // Rides que todavía consideramos activos / relevantes
    $aliveRideStatuses = [
        'requested',
        'offered',
        'bidding_proposed',
        'accepted',
        'en_route',
        'arrived',
        'on_board',
        'scheduled',
    ];

    // Rides que están realmente en curso después de una aceptación
    $runningRideStatuses = [
        'accepted',
        'en_route',
        'arrived',
        'on_board',
    ];

    $q = DB::table('ride_offers as o')
        ->join('rides as r', 'r.id', '=', 'o.ride_id')
        ->where('o.driver_id', $driverId)
        ->where('o.tenant_id', $tenantId);

    $now = now();

    // 1) status vacío o "alive"
    if ($status === '' || $status === 'alive') {

        $q->whereIn('o.status', $aliveOfferStatuses)
          ->where('o.expires_at', '>', $now)
          ->whereIn('r.status', $aliveRideStatuses);

    // 2) status explícito y válido
    } elseif (in_array($status, $validOfferStatuses, true)) {

        $q->where('o.status', $status);

        if ($status === 'accepted') {
            $q->whereIn('r.status', $runningRideStatuses);
        }

        if (in_array($status, $aliveOfferStatuses, true)) {
            $q->where('o.expires_at', '>', $now)
              ->whereIn('r.status', $aliveRideStatuses);
        }

    // 3) status inválido → fallback a "alive"
    } else {

        $q->whereIn('o.status', $aliveOfferStatuses)
          ->where('o.expires_at', '>', $now)
          ->whereIn('r.status', $aliveRideStatuses);
    }

    $items = $q
        ->orderByDesc('o.id')
        ->select([
            'o.id              as offer_id',
            'o.status          as offer_status',
            'o.sent_at',
            'o.responded_at',
            'o.is_direct',
            'o.expires_at',
            'o.round_no',
            'o.eta_seconds',
            'o.distance_m',

            'r.id              as ride_id',
            'r.status          as ride_status',
            'r.requested_channel',
            'r.passenger_name',
            'r.passenger_phone',
            'r.pax',
            'r.notes',

            'r.origin_lat',
            'r.origin_lng',
            'r.origin_label',
            'r.dest_lat',
            'r.dest_lng',
            'r.dest_label',

            'r.quoted_amount',
            'r.passenger_offer',
            'r.agreed_amount',
            'r.allow_bidding',

            DB::raw("
                CASE
                    WHEN r.agreed_amount IS NOT NULL THEN r.agreed_amount
                    WHEN r.passenger_offer IS NOT NULL THEN r.passenger_offer
                    ELSE r.quoted_amount
                END as amount
            "),

            'r.distance_m      as ride_distance_m',
            'r.duration_s      as ride_duration_s',
            'r.stops_json',
        ])
        ->get();

    // 🔧 Normalización de tipos antes del JSON
    $items = $items->map(function ($row) {
        // ---- STOPS ----
        $stops = [];
        if (!empty($row->stops_json)) {
            $stops = json_decode($row->stops_json, true) ?: [];
        }
        $row->stops       = $stops;
        $row->stops_count = count($stops);
        unset($row->stops_json);

        // ---- CAMPOS DE BIDDING / MONTO ----

        // allow_bidding: 0/1 → bool (para Moshi)
        if (isset($row->allow_bidding)) {
            $row->allow_bidding = (bool) $row->allow_bidding;
        } else {
            $row->allow_bidding = false;
        }

        // garantizar montos como float o null
        $row->quoted_amount = isset($row->quoted_amount)
            ? (float) $row->quoted_amount
            : null;

        $row->passenger_offer = isset($row->passenger_offer)
            ? (float) $row->passenger_offer
            : null;

        $row->agreed_amount = isset($row->agreed_amount)
            ? (float) $row->agreed_amount
            : null;

        $row->amount = isset($row->amount)
            ? (float) $row->amount
            : null;

        return $row;
    });

    return response()->json([
        'ok'    => true,
        'count' => $items->count(),
        'items' => $items,
    ]);
}




    public function show($offerId, Request $req)
    {
        try {
            $user = $req->user();
            $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
            if (!$driverId) {
                return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
            }

            $o = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->where('o.id', (int)$offerId)
                ->where('o.driver_id', $driverId)
                ->select([
                    // --- offer ---
                    'o.id as offer_id',
                    'o.tenant_id',
                    'o.ride_id',
                    'o.driver_id',
                    'o.vehicle_id',
                    'o.status as offer_status',
                    'o.response',
                    'o.sent_at',
                    'o.responded_at',
                    'o.expires_at',
                    'o.eta_seconds',
                    'o.distance_m',
                    'o.round_no',
                    'o.is_direct',
                    'o.queued_at',
                    'o.queued_position',
                    'o.queued_reason',

                    // --- ride ---
                    'r.status as ride_status',
                    'r.requested_channel',
                    'r.passenger_name', 'r.passenger_phone',
                    'r.origin_label', 'r.origin_lat', 'r.origin_lng',
                    'r.dest_label', 'r.dest_lat', 'r.dest_lng',
                    'r.pax',
                    'r.distance_m as ride_distance_m',
                    'r.duration_s as ride_duration_s',
                    'r.notes',
                    'r.stops_json', 'r.stops_count', 'r.stop_index',

                    // tarifas / bidding
                    'r.total_amount',
                    'r.quoted_amount',
                    'r.allow_bidding',
                    'r.passenger_offer',
                    'r.driver_offer',
                    'r.agreed_amount',
                ])
                ->first();

            if (!$o) {
                return response()->json(['ok' => false, 'msg' => 'Offer not found'], 404);
            }

            // Normalización
            $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
            $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
            $o->dest_lat   = isset($o->dest_lat)   ? (float)$o->dest_lat   : null;
            $o->dest_lng   = isset($o->dest_lng)   ? (float)$o->dest_lng   : null;

            $o->eta_seconds     = isset($o->eta_seconds)     ? (int)$o->eta_seconds     : null;
            $o->distance_m      = isset($o->distance_m)      ? (int)$o->distance_m      : null;
            $o->round_no        = isset($o->round_no)        ? (int)$o->round_no        : null;
            $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m : null;
            $o->ride_duration_s = isset($o->ride_duration_s) ? (int)$o->ride_duration_s : null;
            $o->pax             = isset($o->pax)             ? (int)$o->pax             : null;

            $o->total_amount    = isset($o->total_amount)    ? (float)$o->total_amount    : null;
            $o->quoted_amount   = isset($o->quoted_amount)   ? (float)$o->quoted_amount   : null;
            $o->passenger_offer = isset($o->passenger_offer) ? (float)$o->passenger_offer : null;
            $o->driver_offer    = isset($o->driver_offer)    ? (float)$o->driver_offer    : null;
            $o->agreed_amount   = isset($o->agreed_amount)   ? (float)$o->agreed_amount   : null;
            $o->allow_bidding   = isset($o->allow_bidding)   ? (int)$o->allow_bidding     : null;

            if (!isset($o->is_direct)) {
                $o->is_direct = ($o->round_no === null || (int)$o->round_no === 0) ? 1 : 0;
            } else {
                $o->is_direct = (int)$o->is_direct;
            }

            $o->stops_count = isset($o->stops_count) ? (int)$o->stops_count : 0;
            $o->stop_index  = isset($o->stop_index)  ? (int)$o->stop_index  : 0;
            $o->stops = [];
            if (!empty($o->stops_json)) {
                $tmp = json_decode($o->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $o->stops = array_values(array_map(function ($s) {
                        return [
                            'lat'   => isset($s['lat'])   ? (float)$s['lat']   : null,
                            'lng'   => isset($s['lng'])   ? (float)$s['lng']   : null,
                            'label' => isset($s['label']) ? (string)$s['label'] : null,
                        ];
                    }, $tmp));
                    $o->stops_count = count($o->stops);
                }
            }
            unset($o->stops_json);

            return response()->json(['ok' => true, 'item' => $o]);

        } catch (\Exception $e) {
            \Log::error('OfferController@show error', ['offerId' => $offerId, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * ACEPTAR oferta (sin bidding). Aquí ya haces el match ride–driver.
     * El bid del driver se maneja en el método bid().
     */
    public function accept(int $offerId, Request $req)
{
    try {
        $user     = $req->user();
        $driverId = (int) DB::table('drivers')->where('user_id', $user->id)->value('id');
        $tenantId = (int) ($user->tenant_id ?? 0);

        if (!$driverId || !$tenantId) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado'], 401);
        }

        \Log::info('Accept offer attempt', ['offerId' => $offerId, 'driverId' => $driverId, 'tenantId' => $tenantId]);

        $chk = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->where('o.id', $offerId)
            ->select(
                'o.id',
                'o.driver_id',
                'o.status',
                'o.expires_at',
                'r.id as ride_id',
                'r.tenant_id',
                'r.requested_channel'
            )
            ->first();

        if (!$chk) {
            return response()->json(['ok' => false, 'msg' => 'Offer no encontrada'], 404);
        }

        if ((int) $chk->driver_id !== $driverId || (int) $chk->tenant_id !== $tenantId) {
            return response()->json(['ok' => false, 'msg' => 'Offer inválida o de otro tenant'], 404);
        }

        // Validar expiración
        if (!empty($chk->expires_at)) {
            $expiresAt = Carbon::parse($chk->expires_at);
            if (Carbon::now()->greaterThan($expiresAt)) {
                return response()->json(['ok' => false, 'msg' => 'Offer expirada'], 409);
            }
        }

        // SP que decide si activa o cola el ride
        \Log::info('Calling SP for offer', ['offerId' => $offerId]);
        $row = DB::selectOne("CALL sp_accept_offer_v7(?)", [$offerId]);

        if (!$row) {
            throw new \Exception("Stored procedure returned no result");
        }

        $mode   = $row->mode   ?? 'accepted';
        $rideId = (int) ($row->ride_id ?? 0);

        \Log::info('SP result', ['mode' => $mode, 'rideId' => $rideId]);

        if (!$rideId) {
            return response()->json(['ok' => false, 'msg' => 'Offer no disponible'], 409);
        }

        // Leer ride completo (incluye agreed_amount)
        $ride = DB::table('rides')
            ->where('id', $rideId)
            ->select('id', 'requested_channel', 'agreed_amount', 'passenger_offer', 'quoted_amount', 'total_amount')
            ->first();

        // Validar/leer bid_amount solo para canales que NO son passenger_app
        $v   = $req->validate(['bid_amount' => 'nullable|numeric|min:0']);
        $bid = $v['bid_amount'] ?? null;

        if (
            $bid !== null &&
            ($ride->requested_channel ?? null) !== 'passenger_app'
        ) {
            $settings = DispatchSettingsService::forTenant($tenantId);
            if ($settings->allow_fare_bidding ?? false) {
                DB::table('rides')->where('id', $rideId)->update([
                    'total_amount' => (float) $bid,
                    'updated_at'   => Carbon::now(),
                ]);
                $ride->total_amount = (float) $bid;
            }
        }

        // responded_at
        DB::table('ride_offers')->where('id', $offerId)->update([
            'responded_at' => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        // evento de estado al driver (offers.update)
        OfferBroadcaster::emitStatus($tenantId, $driverId, $rideId, $offerId, 'accepted');
        OfferBroadcaster::queueRemove($tenantId, $driverId, $rideId);

        // liberar otros drivers
        $losers = DB::table('ride_offers as o2')
            ->join('drivers as d', 'd.id', '=', 'o2.driver_id')
            ->where('o2.ride_id', $rideId)
            ->where('o2.driver_id', '<>', $driverId)
            ->whereIn('o2.status', ['offered', 'queued', 'pending_passenger'])
            ->select('o2.id', 'o2.driver_id', 'd.tenant_id')
            ->get();

        foreach ($losers as $lo) {
            DB::table('ride_offers')->where('id', $lo->id)->update([
                'status'       => 'released',
                'responded_at' => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
            OfferBroadcaster::emitStatus(
                (int) $lo->tenant_id,
                (int) $lo->driver_id,
                $rideId,
                (int) $lo->id,
                'released'
            );
        }

        // señal RT al driver + pasajero
        try {
            if ($mode === 'activated') {
                Realtime::toDriver($tenantId, $driverId)->emit('ride.active', [
                    'ride_id'  => $rideId,
                    'offer_id' => $offerId,
                ]);

                 // Leer montos desde DB para resolver la cantidad final
                $raw = DB::table('rides')
                    ->where('id', $rideId)
                    ->select(
                        'requested_channel',
                        'agreed_amount',
                        'passenger_offer',
                        'total_amount',
                        'quoted_amount'
                    )
                    ->first();

                $agreedAmount = 0.0;
                if ($raw) {
                    $channel = $raw->requested_channel ?? null;

                    if ($channel === 'passenger_app') {
                        $base = $raw->agreed_amount
                            ?? $raw->passenger_offer
                            ?? $raw->total_amount
                            ?? $raw->quoted_amount
                            ?? 0;
                    } else {
                        $base = $raw->total_amount
                            ?? $raw->agreed_amount
                            ?? $raw->passenger_offer
                            ?? $raw->quoted_amount
                            ?? 0;
                    }

                    $agreedAmount = (float) round((float) $base);
                }

                // 🔹 Si viene de passenger_app, notificamos bidResult(accepted) con el mismo monto redondeado
                if (($ride->requested_channel ?? null) === 'passenger_app') {
                    RideBroadcaster::bidResult(
                        tenantId:     $tenantId,
                        rideId:       $rideId,
                        offerId:      $offerId,
                        result:       'accepted',
                        agreedAmount: (int) $agreedAmount
                    );
                }


                // Igual que antes: afterAccept manda ride.update + bootstrap de ubicación
                RideBroadcaster::afterAccept(
                    tenantId:     $tenantId,
                    rideId:       $rideId,
                    driverId:     $driverId,
                    offerId:      $offerId,
                    agreedAmount: $agreedAmount > 0 ? $agreedAmount : null,
                );
            } elseif ($mode === 'queued') {
                Realtime::toDriver($tenantId, $driverId)->emit('ride.queued', [
                    'ride_id'  => $rideId,
                    'offer_id' => $offerId,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Error emitting realtime event', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok'       => true,
            'mode'     => $mode,
            'ride_id'  => $rideId,
            'offer_id' => $offerId,
            'status'   => 'accepted',
        ]);

    } catch (\Exception $e) {
        \Log::error('OfferController@accept error', [
            'offerId' => $offerId,
            'error'   => $e->getMessage(),
        ]);

        return response()->json([
            'ok'  => false,
            'msg' => 'Error interno del servidor: ' . $e->getMessage(),
        ], 500);
    }
}


    /**
     * BID del driver: propone un monto al pasajero.
     * NO activa el ride, solo deja la oferta en status pending_passenger.
     */
    public function bid(Request $req, int $offer)
    {
        $data = $req->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $tenantId = (int) ($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);

        $driverId = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $req->user()->id)
            ->value('id');

        if (!$driverId) {
            return response()->json(['ok' => false, 'msg' => 'Driver no encontrado'], 404);
        }

        $amount = (float) $data['amount'];

        DB::beginTransaction();
        try {
            // 1) Oferta
            $o = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $offer)
                ->lockForUpdate()
                ->first();

            if (!$o) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Offer no encontrada'], 404);
            }

            if ((int) $o->driver_id !== (int) $driverId) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Offer no pertenece a este driver'], 403);
            }

            // expiración
            if (!empty($o->expires_at)) {
                $expiresAt = Carbon::parse($o->expires_at);
                if (Carbon::now()->greaterThan($expiresAt)) {
                    DB::rollBack();
                    return response()->json(['ok' => false, 'msg' => 'Offer expirada'], 409);
                }
            }

            // sólo si sigue viva
            if (!in_array($o->status, ['offered', 'queued', 'pending_passenger'], true)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Offer ya no disponible'], 409);
            }

            // 2) Ride
            $ride = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $o->ride_id)
                ->lockForUpdate()
                ->first();

            if (!$ride) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
            }

            $rideStatus = strtolower($ride->status ?? '');
            if (!in_array($rideStatus, ['requested', 'offered'], true)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride no elegible para bidding'], 409);
            }

            if (($ride->requested_channel ?? '') !== 'passenger_app' || !(int) ($ride->allow_bidding ?? 0)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Bidding no habilitado para este ride'], 409);
            }

            // regla: mínimo lo que ofreció el pasajero
            $passengerOffer = $ride->passenger_offer !== null ? (float) $ride->passenger_offer : null;
            if ($passengerOffer !== null && $amount < $passengerOffer) {
                $amount = $passengerOffer;
            }

            // 3) guardar propuesta
            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $offer)
                ->update([
                    'driver_offer' => $amount,
                    'status'       => 'pending_passenger',
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $o->ride_id)
                ->update([
                    'driver_offer' => $amount,
                    'status'       => $rideStatus === 'requested' ? 'offered' : $rideStatus,
                    'updated_at'   => now(),
                ]);

            DB::commit();

            // notificar passenger/panel
            RideBroadcaster::bidProposed(
                tenantId:       $tenantId,
                rideId:         (int) $o->ride_id,
                offerId:        (int) $o->id,
                driverId:       (int) $driverId,
                driverAmount:   $amount,
                passengerOffer: $passengerOffer,
            );

            // actualizar estado en driver
            OfferBroadcaster::emitStatus(
                tenantId: $tenantId,
                driverId: (int) $driverId,
                rideId:   (int) $o->ride_id,
                offerId:  (int) $o->id,
                status:   'pending_passenger',
            );

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('OfferController@bid error', [
                'offer' => $offer,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'msg' => 'Error interno del servidor'], 500);
        }
    }

    public function reject($offerId, Request $req)
    {
        try {
            $user     = $req->user();
            $driverId = (int) DB::table('drivers')->where('user_id', $user->id)->value('id');
            if (!$driverId) {
                return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
            }

            $row = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->where('o.id', $offerId)
                ->select(
                    'o.id',
                    'o.driver_id',
                    'o.status',
                    'o.expires_at',
                    'o.tenant_id',
                    'o.ride_id',
                    'r.tenant_id as r_tenant'
                )
                ->first();

            if (!$row || (int) $row->driver_id !== $driverId) {
                return response()->json(['ok' => false, 'msg' => 'Offer not found'], 404);
            }

            $tenantId = (int) ($row->tenant_id ?? $row->r_tenant ?? 0);

            if (!empty($row->expires_at)) {
                $expiresAt = Carbon::parse($row->expires_at);
                if (Carbon::now()->greaterThan($expiresAt)) {
                    DB::table('ride_offers')->where('id', $offerId)->update([
                        'status'       => 'expired',
                        'responded_at' => Carbon::now(),
                        'updated_at'   => Carbon::now(),
                    ]);
                    return response()->json(['ok' => false, 'msg' => 'Offer expirada'], 409);
                }
            }

            $updated = DB::table('ride_offers')->where('id', $offerId)
                ->whereIn('status', ['offered', 'queued', 'pending_passenger'])
                ->update([
                    'status'       => 'rejected',
                    'responded_at' => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                ]);

            if ($updated) {
                OfferBroadcaster::emitStatus(
                    $tenantId,
                    $driverId,
                    (int) $row->ride_id,
                    (int) $offerId,
                    'rejected'
                );
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            \Log::error('OfferController@reject error', ['offerId' => $offerId, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno del servidor'], 500);
        }
    }


    /**
     * Marcar que el driver está viendo / dejó de ver el detalle de una oferta.
     *
     * POST /api/offers/{offer}/viewing
     * body: { "status": "start" | "stop" }
     */

    public function viewing(Request $req, int $offerId)
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

            // Tenant
            $tid = $req->header('X-Tenant-ID') ?? $user->tenant_id;
            if (!$tid) {
                return response()->json(['ok' => false, 'msg' => 'Usuario sin tenant'], 403);
            }
            $tenantId = (int) $tid;

            // start | stop
            $data = $req->validate([
                'status' => 'required|string|in:start,stop',
            ]);
            $status = $data['status'];

            // Estados vivos de la oferta
            $aliveOfferStatuses = ['offered', 'queued', 'pending_passenger'];

            // Leemos oferta + ride + driver
            $row = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->join('drivers as d', 'd.id', '=', 'o.driver_id')
                ->where('o.id', $offerId)
                ->where('o.driver_id', $driverId)
                ->select([
                    'o.id          as offer_id',
                    'o.tenant_id   as tenant_id',
                    'o.ride_id     as ride_id',
                    'o.status      as offer_status',
                    'o.expires_at  as expires_at',
                    'o.eta_seconds as eta_seconds',
                    'o.distance_m  as distance_m',

                    'r.requested_channel',
                    'r.passenger_id',

                    'd.id          as driver_id',
                    'd.name        as driver_name',
                    'd.phone       as driver_phone',
                    'd.foto_path   as driver_foto_path',
                ])
                ->first();

            if (!$row) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Offer not found',
                ], 404);
            }

            // Validar expiración
            if (!empty($row->expires_at)) {
                $expiresAt = \Carbon\Carbon::parse($row->expires_at);
                if (now()->greaterThan($expiresAt)) {
                    \Log::info('OfferController@viewing: offer expirada, ignorando', [
                        'offer_id' => $offerId,
                        'status'   => $status,
                        'expires'  => $row->expires_at,
                    ]);

                    return response()->json([
                        'ok'      => true,
                        'ignored' => 'expired',
                    ]);
                }
            }

            // Validar que siga viva
            if (!in_array($row->offer_status, $aliveOfferStatuses, true)) {
                \Log::info('OfferController@viewing: offer no viva, ignorando', [
                    'offer_id'     => $offerId,
                    'status'       => $status,
                    'offer_status' => $row->offer_status,
                ]);

                return response()->json([
                    'ok'      => true,
                    'ignored' => 'not_alive',
                ]);
            }

            // Solo tiene sentido mandar este evento si viene de passenger_app
            if (($row->requested_channel ?? null) !== 'passenger_app') {
                \Log::info('OfferController@viewing skip: not passenger_app', [
                    'offer_id' => $offerId,
                    'ride_id'  => $row->ride_id,
                    'channel'  => $row->requested_channel,
                ]);

                return response()->json([
                    'ok'      => true,
                    'skipped' => true,
                    'reason'  => 'not_passenger_app',
                ]);
            }

            // ==== Construir avatar_url igual que en driverCardForPassenger ====
            $avatarUrl = null;
            if (!empty($row->driver_foto_path)) {
                $base = $req->getSchemeAndHttpHost(); // p.ej. https://tudominio.com
                $avatarUrl = $base . '/storage/' . ltrim($row->driver_foto_path, '/');
            }

            \Log::info('OfferController@viewing: driver viewing offer', [
                'tenant_id'   => $tenantId,
                'driver_id'   => $driverId,
                'offer_id'    => $offerId,
                'ride_id'     => $row->ride_id,
                'status'      => $status,
                'driver_name' => $row->driver_name,
                'avatar_url'  => $avatarUrl,
            ]);

            // ==== Payload para el evento hacia Passenger ====
            $driverData = [
                'name'          => $row->driver_name,
                'avatar_url'    => $avatarUrl,
                'vehicle_label' => null, // luego lo llenamos con join a vehicles si quieres
                'vehicle_plate' => null,
                'eta_seconds'   => $row->eta_seconds !== null ? (int) $row->eta_seconds : null,
                'distance_m'    => $row->distance_m !== null ? (int) $row->distance_m : null,
            ];

            // 🔔 Emitimos evento a Passenger (canal tenant.{tenant}.ride.{ride})
            RideBroadcaster::offerViewing(
                tenantId: (int) ($row->tenant_id ?? $tenantId),
                rideId:   (int) $row->ride_id,
                offerId:  (int) $row->offer_id,
                driverId: (int) $row->driver_id,
                status:   $status,   // "start" | "stop"
                driver:   $driverData,
            );

            return response()->json([
                'ok'       => true,
                'status'   => $status,
                'ride_id'  => (int) $row->ride_id,
                'offer_id' => (int) $row->offer_id,

                // Campos útiles también para debug / panel
                'driver_id'    => (int) $row->driver_id,
                'driver_name'  => $row->driver_name,
                'driver_phone' => $row->driver_phone,
                'avatar_url'   => $avatarUrl,
                'eta_seconds'  => $row->eta_seconds,
                'distance_m'   => $row->distance_m,
            ]);

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




    public function debugServerTime(Request $req)
    {
        $timezone = 'America/Mexico_City';

        return response()->json([
            'server' => [
                'utc'      => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'mexico'   => Carbon::now($timezone)->format('Y-m-d H:i:s'),
                'timezone' => $timezone,
                'config_timezone' => config('app.timezone'),
            ],
            'database_expires_example' => [
                'raw'           => '2025-11-11 23:29:25',
                'parsed_utc'    => Carbon::createFromFormat('Y-m-d H:i:s', '2025-11-11 23:29:25', 'UTC')->format('Y-m-d H:i:s'),
                'parsed_mexico' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-11-11 23:29:25', $timezone)->format('Y-m-d H:i:s'),
            ],
        ]);
    }




}
