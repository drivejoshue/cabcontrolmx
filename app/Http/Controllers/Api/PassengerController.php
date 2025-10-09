<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Ride;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    // GET /api/passengers/last-ride?phone=...
    public function lastRide(Request $req)
    {
        $phone = trim((string) $req->query('phone',''));
        if ($phone === '') return response()->json(null);

        $tenantId = $req->header('X-Tenant-ID')
            ?? optional($req->user())->tenant_id
            ?? 1;

        $last = Ride::where('tenant_id', $tenantId)
            ->where('passenger_phone', $phone)
            ->orderByDesc('id')
            ->first();

        if (!$last) return response()->json(null);

        return [
            'passenger_name' => $last->passenger_name,
            'notes'          => $last->notes,
            'origin_label'   => $last->origin_label,
            'origin_lat'     => $last->origin_lat,
            'origin_lng'     => $last->origin_lng,
            'dest_label'     => $last->dest_label,
            'dest_lat'       => $last->dest_lat,
            'dest_lng'       => $last->dest_lng,
        ];
    }

    // GET /api/passengers/lookup?phone=...
    public function lookup(Request $req)
    {
        $phone = trim((string) $req->query('phone',''));
        if ($phone === '') return response()->json(null);

        $tenantId = $req->header('X-Tenant-ID')
            ?? optional($req->user())->tenant_id
            ?? 1;

        $p = Passenger::where(['tenant_id'=>$tenantId,'phone'=>$phone])->first();
        return $p ?: response()->json(null);
    }
}
