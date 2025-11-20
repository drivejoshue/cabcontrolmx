<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OfferBroadcaster;
use App\Services\RideBroadcaster;
use Carbon\Carbon;

class OfferController extends Controller
{
    // Helper para obtener timezone del tenant
    private function tenantTz($tenantId)
    {
        // Por defecto America/Mexico_City, puedes ajustar según tu DB
        return 'America/Mexico_City';
    }

    public function index(Request $req)
    {
        try {
            $user = $req->user();
            $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
            if (!$driverId) {
                return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
            }

            $tenantId = DB::table('drivers')->where('id', $driverId)->value('tenant_id') ?? 1;

            $status = strtolower(trim($req->query('status', '')));
            $valid = ['offered', 'accepted', 'rejected', 'expired', 'canceled', 'released', 'queued'];
            $filterStatus = in_array($status, $valid) ? $status : null;

            $q = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->where('o.tenant_id', $tenantId)
                ->where('o.driver_id', $driverId)
                ->when($filterStatus, fn($qq) => $qq->where('o.status', $filterStatus))
                ->orderByDesc('o.id')
                ->select([
                    // ---- offer ----
                    'o.id as offer_id',
                    'o.status as offer_status',
                    'o.sent_at', 'o.responded_at', 'o.expires_at',
                    'o.eta_seconds', 'o.distance_m', 'o.round_no',
                    'o.is_direct',

                    // ---- ride ----
                    'r.id as ride_id', 'r.status as ride_status',
                    'r.origin_label', 'r.origin_lat', 'r.origin_lng',
                    'r.dest_label', 'r.dest_lat', 'r.dest_lng',
                    'r.quoted_amount', 'r.distance_m as ride_distance_m',
                    'r.duration_s as ride_duration_s',
                    'r.passenger_name', 'r.passenger_phone', 'r.requested_channel', 'r.pax',

                    // ---- stops ----
                    'r.stops_json', 'r.stops_count', 'r.stop_index', 'r.notes',
                ]);

            $offers = $q->limit(100)->get();

            $offers->transform(function ($o) {
                // geos
                $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
                $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
                $o->dest_lat = isset($o->dest_lat) ? (float)$o->dest_lat : null;
                $o->dest_lng = isset($o->dest_lng) ? (float)$o->dest_lng : null;

                // numéricos
                $o->quoted_amount = isset($o->quoted_amount) ? (float)$o->quoted_amount : null;
                $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m : null;
                $o->ride_duration_s = isset($o->ride_duration_s) ? (int)$o->ride_duration_s : null;
                $o->distance_m = isset($o->distance_m) ? (int)$o->distance_m : null;
                $o->eta_seconds = isset($o->eta_seconds) ? (int)$o->eta_seconds : null;
                $o->round_no = isset($o->round_no) ? (int)$o->round_no : 0;

                // is_direct (fallback en PHP, no en SQL)
                if (!isset($o->is_direct)) {
                    $o->is_direct = ($o->round_no === 0 || $o->round_no === null) ? 1 : 0;
                } else {
                    $o->is_direct = (int)$o->is_direct;
                }

                // stops
                $o->stops_count = isset($o->stops_count) ? (int)$o->stops_count : 0;
                $o->stop_index = isset($o->stop_index) ? (int)$o->stop_index : 0;
                $o->stops = [];
                if (!empty($o->stops_json)) {
                    $tmp = json_decode($o->stops_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                        $o->stops = array_values(array_map(function ($s) {
                            return [
                                'lat' => isset($s['lat']) ? (float)$s['lat'] : null,
                                'lng' => isset($s['lng']) ? (float)$s['lng'] : null,
                                'label' => isset($s['label']) ? (string)$s['label'] : null,
                            ];
                        }, $tmp));
                        $o->stops_count = count($o->stops);
                    }
                }
                unset($o->stops_json);

                return $o;
            });

            return response()->json([
                'ok' => true,
                'driver' => ['id' => $driverId, 'tenant_id' => $tenantId],
                'count' => $offers->count(),
                'items' => $offers,
            ]);

        } catch (\Exception $e) {
            \Log::error('OfferController@index error', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno del servidor'], 500);
        }
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

            // Normalización de tipos
            $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
            $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
            $o->dest_lat = isset($o->dest_lat) ? (float)$o->dest_lat : null;
            $o->dest_lng = isset($o->dest_lng) ? (float)$o->dest_lng : null;

            $o->eta_seconds = isset($o->eta_seconds) ? (int)$o->eta_seconds : null;
            $o->distance_m = isset($o->distance_m) ? (int)$o->distance_m : null;
            $o->round_no = isset($o->round_no) ? (int)$o->round_no : null;

            $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m : null;
            $o->ride_duration_s = isset($o->ride_duration_s) ? (int)$o->ride_duration_s : null;
            $o->pax = isset($o->pax) ? (int)$o->pax : null;

            $o->total_amount = isset($o->total_amount) ? (float)$o->total_amount : null;
            $o->quoted_amount = isset($o->quoted_amount) ? (float)$o->quoted_amount : null;
            $o->passenger_offer = isset($o->passenger_offer) ? (float)$o->passenger_offer : null;
            $o->driver_offer = isset($o->driver_offer) ? (float)$o->driver_offer : null;
            $o->agreed_amount = isset($o->agreed_amount) ? (float)$o->agreed_amount : null;
            $o->allow_bidding = isset($o->allow_bidding) ? (int)$o->allow_bidding : null;

            if (!isset($o->is_direct)) {
                $o->is_direct = ($o->round_no === null || (int)$o->round_no === 0) ? 1 : 0;
            } else {
                $o->is_direct = (int)$o->is_direct;
            }

            $o->stops_count = isset($o->stops_count) ? (int)$o->stops_count : 0;
            $o->stop_index = isset($o->stop_index) ? (int)$o->stop_index : 0;
            $o->stops = [];
            if (!empty($o->stops_json)) {
                $tmp = json_decode($o->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $o->stops = array_values(array_map(function ($s) {
                        return [
                            'lat' => isset($s['lat']) ? (float)$s['lat'] : null,
                            'lng' => isset($s['lng']) ? (float)$s['lng'] : null,
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

    public function accept(int $offerId, Request $req)
    {
        try {
            $user = $req->user();
            $driverId = (int)DB::table('drivers')->where('user_id', $user->id)->value('id');
            $tenantId = (int)($user->tenant_id ?? 0);
            
            if (!$driverId || !$tenantId) {
                return response()->json(['ok' => false, 'msg' => 'No autorizado'], 401);
            }

            \Log::info('Accept offer attempt', ['offerId' => $offerId, 'driverId' => $driverId, 'tenantId' => $tenantId]);

            // Validar que la oferta pertenece al driver
            $chk = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->where('o.id', $offerId)
                ->select('o.id', 'o.driver_id', 'o.status', 'o.expires_at', 'r.id as ride_id', 'r.tenant_id', 'r.requested_channel')
                ->first();

            if (!$chk) {
                return response()->json(['ok' => false, 'msg' => 'Offer no encontrada'], 404);
            }

            if ((int)$chk->driver_id !== $driverId || (int)$chk->tenant_id !== $tenantId) {
                return response()->json(['ok' => false, 'msg' => 'Offer inválida o de otro tenant'], 404);
            }

            // Validar expiración
            if (!empty($chk->expires_at)) {
                $expiresAt = Carbon::parse($chk->expires_at);
                if (Carbon::now()->greaterThan($expiresAt)) {
                    return response()->json(['ok' => false, 'msg' => 'Offer expirada'], 409);
                }
            }

            // Llamar stored procedure
            \Log::info('Calling SP for offer', ['offerId' => $offerId]);
            $row = DB::selectOne("CALL sp_accept_offer_v5(?)", [$offerId]);
            
            if (!$row) {
                throw new \Exception("Stored procedure returned no result");
            }

            $mode = $row->mode ?? 'accepted';
            $rideId = (int)($row->ride_id ?? 0);
            
            \Log::info('SP result', ['mode' => $mode, 'rideId' => $rideId]);

            if (!$rideId) {
                return response()->json(['ok' => false, 'msg' => 'Offer no disponible'], 409);
            }

            // Bid opcional
            $v = $req->validate(['bid_amount' => 'nullable|numeric|min:0']);
            $bid = isset($v['bid_amount']) ? (float)$v['bid_amount'] : null;

            $ride = DB::table('rides')->where('id', $rideId)
                ->select('id', 'requested_channel', 'total_amount')->first();

            if ($bid !== null && ($ride->requested_channel ?? null) === 'passenger_app') {
                $settings = \App\Services\DispatchSettingsService::forTenant($tenantId);
                if ($settings->allow_fare_bidding) {
                    DB::table('rides')->where('id', $rideId)->update([
                        'total_amount' => $bid,
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }

            // Marcar responded_at
            DB::table('ride_offers')->where('id', $offerId)->update([
                'responded_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Emitir eventos
            OfferBroadcaster::emitStatus($tenantId, $driverId, $rideId, $offerId, 'accepted');
            OfferBroadcaster::queueRemove($tenantId, $driverId, $rideId);

            // Liberar otros drivers
            $losers = DB::table('ride_offers as o2')
                ->join('drivers as d', 'd.id', '=', 'o2.driver_id')
                ->where('o2.ride_id', $rideId)
                ->where('o2.driver_id', '<>', $driverId)
                ->whereIn('o2.status', ['offered', 'queued'])
                ->select('o2.id', 'o2.driver_id', 'd.tenant_id')
                ->get();

            foreach ($losers as $lo) {
                DB::table('ride_offers')->where('id', $lo->id)->update([
                    'status' => 'released',
                    'responded_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                OfferBroadcaster::emitStatus((int)$lo->tenant_id, (int)$lo->driver_id, $rideId, (int)$lo->id, 'released');
            }

            // Señal de estado al driver
            try {
                if ($mode === 'activated') {
                    \App\Services\Realtime::toDriver($tenantId, $driverId)->emit('ride.active', [
                        'ride_id' => $rideId,
                        'offer_id' => $offerId,
                    ]);
                } elseif ($mode === 'queued') {
                    \App\Services\Realtime::toDriver($tenantId, $driverId)->emit('ride.queued', [
                        'ride_id' => $rideId,
                        'offer_id' => $offerId,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Error emitting realtime event', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'ok' => true,
                'mode' => $mode,
                'ride_id' => $rideId,
                'offer_id' => $offerId,
                'status' => 'accepted',
            ]);

        } catch (\Exception $e) {
            \Log::error('OfferController@accept error', [
                'offerId' => $offerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'ok' => false,
                'msg' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject($offerId, Request $req)
    {
        try {
            $user = $req->user();
            $driverId = (int)DB::table('drivers')->where('user_id', $user->id)->value('id');
            if (!$driverId) {
                return response()->json(['ok' => false, 'msg' => 'No driver bound'], 400);
            }

            $row = DB::table('ride_offers as o')
                ->join('rides as r', 'r.id', '=', 'o.ride_id')
                ->where('o.id', $offerId)
                ->select('o.id', 'o.driver_id', 'o.status', 'o.expires_at', 'o.tenant_id', 'o.ride_id', 'r.tenant_id as r_tenant')
                ->first();

            if (!$row || (int)$row->driver_id !== $driverId) {
                return response()->json(['ok' => false, 'msg' => 'Offer not found'], 404);
            }

            $tenantId = (int)($row->tenant_id ?? $row->r_tenant ?? 0);

            // Validar expiración
            if (!empty($row->expires_at)) {
                $expiresAt = Carbon::parse($row->expires_at);
                if (Carbon::now()->greaterThan($expiresAt)) {
                    DB::table('ride_offers')->where('id', $offerId)->update([
                        'status' => 'expired',
                        'responded_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                    return response()->json(['ok' => false, 'msg' => 'Offer expirada'], 409);
                }
            }

            // Rechazo normal
            $updated = DB::table('ride_offers')->where('id', $offerId)
                ->whereIn('status', ['offered', 'queued'])
                ->update([
                    'status' => 'rejected',
                    'responded_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            if ($updated) {
                OfferBroadcaster::emitStatus($tenantId, $driverId, (int)$row->ride_id, (int)$offerId, 'rejected');
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            \Log::error('OfferController@reject error', ['offerId' => $offerId, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno del servidor'], 500);
        }
    }

  public function bid(Request $req, int $offer)
{
    $data = $req->validate([
        'amount' => 'required|numeric|min:10',
    ]);

    $tenantId = (int) ($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);

    // 🔹 Driver del token
    $driverId = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $req->user()->id)
        ->value('id');

    if (!$driverId) {
        return response()->json(['ok' => false, 'msg' => 'Driver no encontrado'], 404);
    }

    $amount = (int) $data['amount'];

    DB::beginTransaction();

    // 🔹 1) Oferta específica
    $o = DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('id', $offer)
        ->lockForUpdate()
        ->first();

    if (!$o) {
        DB::rollBack();
        return response()->json(['ok' => false, 'msg' => 'Offer no encontrada'], 404);
    }

    if ((int)$o->driver_id !== (int)$driverId) {
        DB::rollBack();
        return response()->json(['ok' => false, 'msg' => 'Offer no pertenece a este driver'], 403);
    }

    // Solo se puede ofertar si sigue viva
    if (!in_array($o->status, ['offered', 'pending_passenger'], true)) {
        DB::rollBack();
        return response()->json(['ok' => false, 'msg' => 'Offer ya no disponible'], 409);
    }

    // 🔹 2) Ride asociado
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
    if ($rideStatus !== 'requested') {
        DB::rollBack();
        return response()->json(['ok' => false, 'msg' => 'Ride no elegible para bidding'], 409);
    }

    // Debe venir de passenger_app y tener bidding permitido
    if (($ride->requested_channel ?? '') !== 'passenger_app' || !(int)($ride->allow_bidding ?? 0)) {
        DB::rollBack();
        return response()->json(['ok' => false, 'msg' => 'Bidding no habilitado para este ride'], 409);
    }

    // 🔹 3) Reglas de rango (opcional)
    //    - mínimo: lo que ofreció el pasajero
    $passengerOffer = $ride->passenger_offer !== null ? (int)$ride->passenger_offer : null;
    if ($passengerOffer !== null && $amount < $passengerOffer) {
        $amount = $passengerOffer; // o devolver 422 si prefieres
    }

    // 🔹 4) Guardar propuesta del driver en ride_offers
    DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('id', $offer)
        ->update([
            'driver_offer' => $amount,
            'status'       => 'pending_passenger',   // espera respuesta del pasajero
            'responded_at' => now(),
            'updated_at'   => now(),
        ]);

    // (Opcional pero útil) reflejar la última propuesta en rides.driver_offer
    DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $o->ride_id)
        ->update([
            'driver_offer' => $amount,
            'updated_at'   => now(),
        ]);

    // (Opcional) registrar en ride_bids como log
    // DB::table('ride_bids')->insert([
    //     'ride_id'    => $o->ride_id,
    //     'role'       => 'driver',
    //     'amount'     => $amount,
    //     'note'       => null,
    //     'created_at' => now(),
    // ]);

    DB::commit();

    // 🔔 Notificar a Passenger (y panel) que hay oferta de X
    RideBroadcaster::bidProposed(
        tenantId:       $tenantId,
        rideId:         (int) $o->ride_id,
        offerId:        (int) $o->id,
        driverId:       (int) $driverId,
        driverAmount:   $amount,
        passengerOffer: $passengerOffer,
    );

    // 🔔 Actualizar estado de la oferta en el driver
    \App\Services\OfferBroadcaster::emitStatus(
        tenantId: $tenantId,
        driverId: (int) $driverId,
        rideId:   (int) $o->ride_id,
        offerId:  (int) $o->id,
        status:   'pending_passenger',
    );

    return response()->json(['ok' => true]);
}


    // En OfferController.php
public function debugServerTime(Request $req)
{
    $timezone = 'America/Mexico_City';
    
    return response()->json([
        'server' => [
            'utc' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
            'mexico' => Carbon::now($timezone)->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'config_timezone' => config('app.timezone')
        ],
        'database_expires_example' => [
            'raw' => '2025-11-11 23:29:25',
            'parsed_utc' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-11-11 23:29:25', 'UTC')->format('Y-m-d H:i:s'),
            'parsed_mexico' => Carbon::createFromFormat('Y-m-d H:i:s', '2025-11-11 23:29:25', $timezone)->format('Y-m-d H:i:s')
        ]
    ]);
}

}