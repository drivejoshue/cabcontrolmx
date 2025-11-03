<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OfferBroadcaster;

class OfferController extends Controller
{
   // App/Http/Controllers/Api/OfferController.php

public function index(Request $req)
{
    $user = $req->user();
    $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
    if (!$driverId) abort(400, 'No driver bound');

    $tenantId = DB::table('drivers')->where('id',$driverId)->value('tenant_id') ?? 1;

    $status = strtolower(trim($req->query('status','')));
    $valid  = ['offered','accepted','rejected','expired','canceled','released','queued'];
    $filterStatus = in_array($status, $valid) ? $status : null;

    $q = DB::table('ride_offers as o')
        ->join('rides as r','r.id','=','o.ride_id')
        ->where('o.tenant_id', $tenantId)
        ->where('o.driver_id', $driverId)
        ->when($filterStatus, fn($qq)=> $qq->where('o.status',$filterStatus))
        ->orderByDesc('o.id')
        ->select([
            // ---- offer ----
            'o.id as offer_id',
            'o.status as offer_status',
            'o.sent_at','o.responded_at','o.expires_at',
            'o.eta_seconds','o.distance_m','o.round_no',
            // OJO: NO usar COALESCE a nivel SQL (rompe si no existe la columna)
            'o.is_direct',

            // ---- ride ----
            'r.id as ride_id','r.status as ride_status',
            'r.origin_label','r.origin_lat','r.origin_lng',
            'r.dest_label','r.dest_lat','r.dest_lng',
            'r.quoted_amount','r.distance_m as ride_distance_m',
            // Asegúrate que esta columna existe; si tu schema usa route_duration_s cámbiala aquí:
            'r.duration_s as ride_duration_s',
            'r.passenger_name','r.passenger_phone','r.requested_channel','r.pax',

            // ---- stops ----
            'r.stops_json','r.stops_count','r.stop_index','r.notes',
        ]);

    $offers = $q->limit(100)->get();

    $offers->transform(function ($o) {
        // geos
        $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
        $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
        $o->dest_lat   = isset($o->dest_lat)   ? (float)$o->dest_lat   : null;
        $o->dest_lng   = isset($o->dest_lng)   ? (float)$o->dest_lng   : null;

        // numéricos
        $o->quoted_amount   = isset($o->quoted_amount)   ? (float)$o->quoted_amount   : null;
        $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m   : null;
        $o->ride_duration_s = isset($o->ride_duration_s) ? (int)$o->ride_duration_s   : null; // <- FIX
        $o->distance_m      = isset($o->distance_m)      ? (int)$o->distance_m        : null;
        $o->eta_seconds     = isset($o->eta_seconds)     ? (int)$o->eta_seconds       : null;
        $o->round_no        = isset($o->round_no)        ? (int)$o->round_no          : 0;

        // is_direct (fallback en PHP, no en SQL)
        if (!isset($o->is_direct)) {
            $o->is_direct = ($o->round_no === 0 || $o->round_no === null) ? 1 : 0;
        } else {
            $o->is_direct = (int)$o->is_direct;
        }

        // stops
        $o->stops_count = isset($o->stops_count) ? (int)$o->stops_count : 0;
        $o->stop_index  = isset($o->stop_index)  ? (int)$o->stop_index  : 0;
        $o->stops = [];
        if (!empty($o->stops_json)) {
            $tmp = json_decode($o->stops_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $o->stops = array_values(array_map(function($s){
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

        return $o;
    });

    return response()->json([
        'ok'     => true,
        'driver' => ['id'=>$driverId, 'tenant_id'=>$tenantId],
        'count'  => $offers->count(),
        'items'  => $offers,
    ]);
}



public function show($offerId, Request $req)
    {
        $user = $req->user();

        // Driver vinculado al usuario
        $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
        if (!$driverId) abort(400, 'No driver bound');

        // Carga de oferta + ride usando NOMBRES REALES
        $o = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->where('o.id', (int)$offerId)
            ->where('o.driver_id', $driverId) // asegúrate de que le pertenezca
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
                'r.dest_label',   'r.dest_lat',   'r.dest_lng',
                'r.pax',
                'r.distance_m as ride_distance_m',
                'r.duration_s as ride_duration_s',
                'r.notes',
                'r.stops_json','r.stops_count','r.stop_index',

                // tarifas / bidding
                'r.total_amount',
                'r.quoted_amount',
                'r.allow_bidding',
                'r.passenger_offer',
                'r.driver_offer',
                'r.agreed_amount',
            ])
            ->first();

        if (!$o) abort(404, 'Offer not found');

        // ----- Normalización de tipos -----
        // geos
        $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
        $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
        $o->dest_lat   = isset($o->dest_lat)   ? (float)$o->dest_lat   : null;
        $o->dest_lng   = isset($o->dest_lng)   ? (float)$o->dest_lng   : null;

        // offer metrics
        $o->eta_seconds = isset($o->eta_seconds) ? (int)$o->eta_seconds : null;
        $o->distance_m  = isset($o->distance_m)  ? (int)$o->distance_m  : null;
        $o->round_no    = isset($o->round_no)    ? (int)$o->round_no    : null;

        // ride metrics
        $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m : null;
        $o->ride_duration_s = isset($o->ride_duration_s) ? (int)$o->ride_duration_s : null;
        $o->pax             = isset($o->pax)             ? (int)$o->pax             : null;

        // montos / bidding
        $o->total_amount    = isset($o->total_amount)    ? (float)$o->total_amount    : null;
        $o->quoted_amount   = isset($o->quoted_amount)   ? (float)$o->quoted_amount   : null;
        $o->passenger_offer = isset($o->passenger_offer) ? (float)$o->passenger_offer : null;
        $o->driver_offer    = isset($o->driver_offer)    ? (float)$o->driver_offer    : null;
        $o->agreed_amount   = isset($o->agreed_amount)   ? (float)$o->agreed_amount   : null;
        $o->allow_bidding   = isset($o->allow_bidding)   ? (int)$o->allow_bidding     : null;

        // is_direct (fallback si viene null)
        if (!isset($o->is_direct)) {
            $o->is_direct = ($o->round_no === null || (int)$o->round_no === 0) ? 1 : 0;
        } else {
            $o->is_direct = (int)$o->is_direct;
        }

        // stops
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
        unset($o->stops_json); // ya no mandamos el JSON crudo

        return response()->json(['ok' => true, 'item' => $o]);
    }
}



// OfferController@accept
public function accept($offerId, Request $req)
{
    $user = $req->user();
    $driverId = \DB::table('drivers')->where('user_id', $user->id)->value('id');
    if (!$driverId) abort(400, 'No driver bound');

    $v = $req->validate([
        'bid_amount' => 'nullable|numeric|min:0',
    ]);
    $bid = isset($v['bid_amount']) ? (float)$v['bid_amount'] : null;

    // 1) Ejecuta el SP de aceptación (maneja locks/cola/asignación)
    $row = \DB::selectOne("CALL sp_accept_offer_v5(?)", [(int)$offerId]);
    $mode   = $row->mode    ?? 'accepted';  // 'queued' | 'activated'
    $rideId = $row->ride_id ?? null;

    if (!$rideId) {
        return response()->json(['ok'=>false,'msg'=>'Offer no disponible'], 409);
    }

    // 2) Si es Passenger App y viene bidding permitido -> snapshot a total_amount
    //    (NO tocamos total_amount si channel=dispatch)
    $ride = \DB::table('rides')->where('id',$rideId)->select('id','tenant_id','requested_channel','total_amount')->first();
    $tenantId = (int)($ride->tenant_id ?? ($user->tenant_id ?? 1));

    if ($bid !== null && ($ride->requested_channel ?? null) === 'passenger_app') {
        \DB::table('rides')->where('id',$rideId)->update([
            'total_amount' => $bid,
            'updated_at'   => now(),
        ]);
    }
    OfferBroadcaster::emitStatus($tenantId, $driverId, (int)$rideId, (int)$offerId, 'accepted');

    // 3) Emitir eventos por driver (si existen)
    try {
        if ($mode === 'activated') {
            \App\Services\Realtime::toDriver($tenantId, $driverId)->emit('ride.active', [
                'ride_id'  => (int)$rideId,
                'offer_id' => (int)$offerId,
            ]);
        } else { // queued
            // Puedes leer la position si la guardas en el SP; aquí simple notify:
            \App\Services\Realtime::toDriver($tenantId, $driverId)->emit('ride.queued', [
                'ride_id'  => (int)$rideId,
                'offer_id' => (int)$offerId,
            ]);
        }

        \App\Services\Realtime::toDriver($tenantId, $driverId)->emit('offers.update', [
            'ride_id'  => (int)$rideId,
            'offer_id' => (int)$offerId,
            'status'   => 'accepted',
        ]);
    } catch (\Throwable $e) {
        // si WS falla, polling lo corrige — no interrumpimos la aceptación
    }

    return response()->json(['ok'=>true, 'mode'=>$mode, 'ride_id'=>$rideId]);
}



public function reject($offerId, Request $req)
{
    $driverId = DB::table('drivers')->where('user_id', $req->user()->id)->value('id');
    abort_if(!$driverId, 400, 'No driver bound');

    $row = DB::table('ride_offers')->where('id',$offerId)->first();
    abort_if(!$row || (int)$row->driver_id !== (int)$driverId, 404);

    DB::table('ride_offers')->where('id',$offerId)
        ->whereIn('status',['offered','queued'])
        ->update(['status'=>'rejected','responded_at'=>now(),'updated_at'=>now()]);

    \App\Services\OfferBroadcaster::emitStatus((int)$row->tenant_id,(int)$row->driver_id,(int)$row->ride_id,(int)$offerId,'rejected');

    return response()->json(['ok'=>true]);
}


}
