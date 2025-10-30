<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function index(Request $req)
    {
        $user = $req->user();

        $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
        if (!$driverId) abort(400, 'No driver bound');

        $tenantId = DB::table('drivers')->where('id',$driverId)->value('tenant_id') ?? 1;

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
                // ---- offer ----
                'o.id as offer_id',
                'o.status as offer_status',
                'o.sent_at',
                'o.responded_at',
                'o.expires_at',
                'o.eta_seconds',
                'o.distance_m',
                'o.round_no',
                DB::raw("COALESCE(o.is_direct, CASE WHEN o.round_no IS NULL OR o.round_no=0 THEN 1 ELSE 0 END) as is_direct"),

                // ---- ride ----
                'r.id as ride_id',
                'r.status as ride_status',
                'r.origin_label','r.origin_lat','r.origin_lng',
                'r.dest_label','r.dest_lat','r.dest_lng',
                'r.quoted_amount',
                'r.distance_m as ride_distance_m',
                'r.duration_s as ride_duration_s',
                'r.passenger_name','r.passenger_phone','r.requested_channel','r.pax',

                // ---- stops ----
                'r.stops_json','r.stops_count','r.stop_index','r.notes',
            ]);

        $offers = $q->limit(100)->get();

        // === Normalización + decodificación de stops ===
        $offers->transform(function ($o) {
            // numéricos
            $o->origin_lat = isset($o->origin_lat) ? (float)$o->origin_lat : null;
            $o->origin_lng = isset($o->origin_lng) ? (float)$o->origin_lng : null;
            $o->dest_lat   = isset($o->dest_lat)   ? (float)$o->dest_lat   : null;
            $o->dest_lng   = isset($o->dest_lng)   ? (float)$o->dest_lng   : null;

            $o->quoted_amount   = isset($o->quoted_amount)   ? (float)$o->quoted_amount   : null;
            $o->ride_distance_m = isset($o->ride_distance_m) ? (int)$o->ride_distance_m   : null;
            $o->duration_s      = isset($o->duration_s)      ? (int)$o->duration_s        : null;
            $o->distance_m      = isset($o->distance_m)      ? (int)$o->distance_m        : null;
            $o->eta_seconds     = isset($o->eta_seconds)     ? (int)$o->eta_seconds       : null;
            $o->round_no        = isset($o->round_no)        ? (int)$o->round_no          : 0;
            $o->is_direct       = isset($o->is_direct)       ? (int)$o->is_direct         : 0;

            // stops
            $o->stops_count = isset($o->stops_count) ? (int)$o->stops_count : 0;
            $o->stop_index  = isset($o->stop_index)  ? (int)$o->stop_index  : 0;

            $o->stops = [];
            if (!empty($o->stops_json)) {
                $tmp = json_decode($o->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    // normaliza forma y tipos
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

            // opcional: limpia el json crudo para no mandar dos veces
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

    public function accept($offer)
    {
        DB::statement('CALL sp_accept_offer_v3(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }

    public function reject($offer, Request $req)
    {
        DB::statement('CALL sp_reject_offer_v2(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }
}
