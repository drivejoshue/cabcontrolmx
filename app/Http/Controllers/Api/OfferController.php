<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    

    // App/Http/Controllers/Api/RideOfferController.php
public function index(Request $req)
{
    $user = $req->user();

    // driver_id por user_id
    $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
    if (!$driverId) abort(400, 'No driver bound');

    $tenantId = DB::table('drivers')->where('id',$driverId)->value('tenant_id') ?? 1;

    // ?status=offered|accepted|rejected|expired|canceled|released
    $status = strtolower(trim($req->query('status','')));
    $valid  = ['offered','accepted','rejected','expired','canceled','released'];
    $filterStatus = in_array($status, $valid) ? $status : null;

    $q = DB::table('ride_offers as o')
        ->join('rides as r','r.id','=','o.ride_id')
        ->where('o.tenant_id', $tenantId)
        ->where('o.driver_id', $driverId)
        ->when($filterStatus, fn($qq)=> $qq->where('o.status',$filterStatus))
        ->orderByDesc('o.id')
        ->select([
            // oferta
            'o.id as offer_id',
            'o.status as offer_status',
            'o.sent_at',
            'o.responded_at',
            'o.expires_at',
            'o.eta_seconds',
            'o.distance_m',
            'o.round_no',
            // ride
            'r.id as ride_id',
            'r.status as ride_status',
            'r.origin_label','r.origin_lat','r.origin_lng',
            'r.dest_label','r.dest_lat','r.dest_lng',
            'r.quoted_amount','r.distance_m as ride_distance_m','r.duration_s as ride_duration_s',
        ]);

    $offers = $q->limit(100)->get();

    return response()->json([
        'ok'     => true,
        'driver' => ['id'=>$driverId, 'tenant_id'=>$tenantId],
        'count'  => $offers->count(),
        'items'  => $offers,
    ]);
}



    public function accept($offer)
    {
        DB::statement('CALL sp_accept_offer_v3(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }

    public function reject($offer, Request $req)
    {
        // (opcional) validar que esta oferta pertenece al driver autenticado
        DB::statement('CALL sp_reject_offer_v2(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }
}
