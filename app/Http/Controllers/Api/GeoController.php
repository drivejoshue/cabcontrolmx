<?php
// app/Http/Controllers/Api/GeoController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeoController
{
    public function route(Request $req)
    {
        $v = $req->validate([
            'from.lat' => 'required|numeric',
            'from.lng' => 'required|numeric',
            'to.lat'   => 'required|numeric',
            'to.lng'   => 'required|numeric',
            'mode'     => 'nullable|in:driving,walking,bicycling',
        ]);

        $from = $v['from']; $to = $v['to'];
        $mode = $v['mode'] ?? 'driving';

        // 1) Google Directions, si existe KEY
        $googleKey = config('services.google.maps_key'); // .env: GOOGLE_MAPS_KEY=...
        if ($googleKey) {
            try {
                $resp = Http::timeout(8)->get(
                    'https://maps.googleapis.com/maps/api/directions/json',
                    [
                        'origin' => $from['lat'].','.$from['lng'],
                        'destination' => $to['lat'].','.$to['lng'],
                        'mode' => $mode,
                        'key' => $googleKey,
                    ]
                );

                if ($resp->ok() && ($data = $resp->json()) && ($data['status'] ?? '') === 'OK') {
                    $leg = $data['routes'][0]['legs'][0] ?? null;
                    $poly = $data['routes'][0]['overview_polyline']['points'] ?? null;

                    return response()->json([
                        'ok' => true,
                        'provider' => 'google',
                        'distance_m' => $leg['distance']['value'] ?? null,
                        'duration_s' => $leg['duration']['value'] ?? null,
                        'polyline'   => $poly,
                    ]);
                }
            } catch (\Throwable $e) { /* sigue a OSRM */ }
        }

        // 2) OSRM (public demo, sin API key)
        try {
            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%f,%f;%f,%f?overview=full&geometries=polyline',
                $from['lng'], $from['lat'], $to['lng'], $to['lat']
            );
            $resp = Http::timeout(8)->get($url);
            if ($resp->ok() && ($d = $resp->json()) && ($d['code'] ?? '') === 'Ok') {
                $route = $d['routes'][0] ?? null;
                return response()->json([
                    'ok' => true,
                    'provider' => 'osrm',
                    'distance_m' => (int)($route['distance'] ?? 0),
                    'duration_s' => (int)($route['duration'] ?? 0),
                    'polyline'   => $route['geometry'] ?? null, // polyline
                ]);
            }
        } catch (\Throwable $e) { /* cae a fallback */ }

        // 3) Fallback: línea recta
        return response()->json([
            'ok' => true,
            'provider' => 'fallback',
            'distance_m' => null,
            'duration_s' => null,
            'points' => [
                [ (float)$from['lat'], (float)$from['lng'] ],
                [ (float)$to['lat'],   (float)$to['lng']   ],
            ],
        ]);
    }
}
