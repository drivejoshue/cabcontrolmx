<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerNearbyDriversController extends Controller
{
    public function nearby(Request $r)
{
    $data = $r->validate([
        'lat'       => 'required|numeric',
        'lng'       => 'required|numeric',
        'radius_km' => 'nullable|numeric|min:0.3|max:20',
        'limit'     => 'nullable|integer|min:1|max:60',
    ]);

    $lat      = (float)$data['lat'];
    $lng      = (float)$data['lng'];
    $radiusKm = isset($data['radius_km']) ? (float)$data['radius_km'] : 3.0;
    $limit    = isset($data['limit']) ? (int)$data['limit'] : 35;

    // Bounding box para no calcular haversine contra toda la tabla
    $latDelta = $radiusKm / 111.0;
    $cosLat   = max(0.2, cos(deg2rad($lat)));
    $lngDelta = $radiusKm / (111.0 * $cosLat);

    $minLat = $lat - $latDelta;
    $maxLat = $lat + $latDelta;
    $minLng = $lng - $lngDelta;
    $maxLng = $lng + $lngDelta;

    // “Freshness” alineada al SP (120s)
    $freshCut = DB::raw("DATE_SUB(NOW(), INTERVAL 60 SECOND)");

    // Subquery: último row de driver_locations por driver (en este tenant)
    $latestPerDriver = DB::table('driver_locations as dl1')
        ->select('dl1.tenant_id', 'dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
        ->groupBy('dl1.tenant_id', 'dl1.driver_id');

    // Query: drivers idle + shift abierto + vehículo activo + última ubicación fresca
    $rows = DB::table('drivers as d')
        ->join('driver_shifts as s', function ($j) {
            $j->on('s.tenant_id', '=', 'd.tenant_id')
              ->on('s.driver_id', '=', 'd.id')
              ->whereNull('s.ended_at')
              ->where('s.status', '=', 'abierto');
        })
        ->join('vehicles as v', function ($j) {
            $j->on('v.tenant_id', '=', 's.tenant_id')
              ->on('v.id', '=', 's.vehicle_id')
              ->where('v.active', '=', 1);
        })
        // última ubicación real por driver
        ->joinSub($latestPerDriver, 'last', function ($j) {
            $j->on('last.tenant_id', '=', 'd.tenant_id')
              ->on('last.driver_id', '=', 'd.id');
        })
        ->join('driver_locations as dl', function ($j) {
            $j->on('dl.tenant_id', '=', 'last.tenant_id')
              ->on('dl.driver_id', '=', 'last.driver_id')
              ->on('dl.id', '=', 'last.last_id');
        })
        ->where('d.status', 'idle')
        // frescura real por reported_at
        ->where('dl.reported_at', '>=', $freshCut)
        // bounding box usando coords reales
        ->whereBetween('dl.lat', [$minLat, $maxLat])
        ->whereBetween('dl.lng', [$minLng, $maxLng])
        ->select([
            'd.tenant_id',
            'd.id as driver_id',
            'dl.lat as last_lat',
            'dl.lng as last_lng',
            DB::raw('COALESCE(dl.heading_deg, dl.bearing) as last_bearing'),
            's.vehicle_id',
            'v.economico',
            'v.brand',
            'v.model',
            'v.type',
            'v.color',
        ])
        ->selectRaw('haversine_km(?, ?, dl.lat, dl.lng) as distance_km', [$lat, $lng])
        ->havingRaw('distance_km <= ?', [$radiusKm])
        ->orderBy('distance_km', 'asc')
        ->limit($limit)
        ->get();

    if ($rows->isEmpty()) {
        return response()->json(['ok' => true, 'count' => 0, 'items' => []])
            ->header('Cache-Control', 'no-store');
    }

    // Key para public_id (no expone driver_id real)
    $rawKey = (string) config('app.key', 'key');
    $key = str_starts_with($rawKey, 'base64:')
        ? base64_decode(substr($rawKey, 7))
        : $rawKey;

    $items = [];
    foreach ($rows as $row) {
        $tenantId = (int)$row->tenant_id;
        $driverId = (int)$row->driver_id;

        $publicId = substr(hash_hmac('sha256', "{$tenantId}:{$driverId}", $key), 0, 16);

        // Privacidad: redondeo (reduce “tracking fino”)
        $latOut = round((float)$row->last_lat, 4);
        $lngOut = round((float)$row->last_lng, 4);

        $items[] = [
            'id'      => $publicId,
            'lat'     => $latOut,
            'lng'     => $lngOut,
            'bearing' => $row->last_bearing !== null ? (float)$row->last_bearing : null,
            'vehicle' => [
                'economico' => (string)$row->economico,
                'brand'     => $row->brand,
                'model'     => $row->model,
                'type'      => $row->type,
                'color'     => $row->color,
            ],
        ];
    }

    return response()->json([
        'ok'    => true,
        'count' => count($items),
        'items' => $items,
    ])->header('Cache-Control', 'no-store');
}

}
