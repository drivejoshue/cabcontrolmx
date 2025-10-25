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
             // antes: 'nullable|array|max:2' + required_with
    // ahora: "sometimes" para que puedas OMITIR la llave cuando no hay stops
    'stops'        => 'sometimes|array|max:2',
    'stops.*.lat'  => 'required|numeric',
    'stops.*.lng'  => 'required|numeric',
        ]);

        $from  = ['lat'=>(float)$v['from']['lat'], 'lng'=>(float)$v['from']['lng']];
        $to    = ['lat'=>(float)$v['to']['lat'],   'lng'=>(float)$v['to']['lng']];
        $mode  = $v['mode'] ?? 'driving';
        $stops = array_map(fn($s)=>['lat'=>(float)$s['lat'],'lng'=>(float)$s['lng']], $v['stops'] ?? []);

        // ---------- 1) Google Directions con waypoints ----------
        $googleKey = config('services.google.maps_key') ?? config('services.google.maps.key');
        if ($googleKey) {
            try {
                $params = [
                    'origin'      => "{$from['lat']},{$from['lng']}",
                    'destination' => "{$to['lat']},{$to['lng']}",
                    'mode'        => $mode,
                    'key'         => $googleKey,
                ];
                if (!empty($stops)) {
                    // via: asegura que pase por la parada SIN “pararse”
                    $params['waypoints'] = implode('|',
                        array_map(fn($s)=>"via:{$s['lat']},{$s['lng']}", $stops)
                    );
                }
                $resp = Http::timeout(10)->get(
                    'https://maps.googleapis.com/maps/api/directions/json',
                    $params
                );

                if ($resp->ok() && ($data = $resp->json()) && ($data['status'] ?? '') === 'OK') {
                    $route = $data['routes'][0] ?? null;
                    $legs  = $route['legs'] ?? [];
                    $distance = 0; $duration = 0;
                    foreach ($legs as $leg) {
                        $distance += (int)($leg['distance']['value'] ?? 0);
                        $duration += (int)($leg['duration']['value'] ?? 0);
                    }
                    $poly = $route['overview_polyline']['points'] ?? null;

                    return response()->json([
                        'ok'         => true,
                        'provider'   => 'google',
                        'distance_m' => $distance,
                        'duration_s' => $duration,
                        'polyline'   => $poly,
                    ]);
                }
            } catch (\Throwable $e) { /* cae a OSRM */ }
        }

        // ---------- 2) OSRM multipunto ----------
        try {
            $seq = array_merge([$from], $stops, [$to]); // O -> S... -> D
            $coords = collect($seq)
                ->map(fn($p)=>sprintf('%f,%f', $p['lng'], $p['lat']))  // lng,lat
                ->implode(';');

            $url = "https://router.project-osrm.org/route/v1/driving/{$coords}?overview=full&geometries=polyline";
            $resp = Http::timeout(10)->get($url);

            if ($resp->ok() && ($d = $resp->json()) && ($d['code'] ?? '') === 'Ok') {
                $route = $d['routes'][0] ?? null;
                return response()->json([
                    'ok'         => true,
                    'provider'   => 'osrm',
                    'distance_m' => (int)($route['distance'] ?? 0),
                    'duration_s' => (int)($route['duration'] ?? 0),
                    'polyline'   => $route['geometry'] ?? null,
                ]);
            }
        } catch (\Throwable $e) { /* cae a fallback */ }

        // ---------- 3) Fallback: línea recta O->S...->D ----------
        $points = array_map(fn($p)=>[(float)$p['lat'], (float)$p['lng']], array_merge([$from], $stops, [$to]));
        return response()->json([
            'ok'         => true,
            'provider'   => 'fallback',
            'distance_m' => null,
            'duration_s' => null,
            'points'     => $points, // para que el front SI pueda dibujar
        ]);
    }
}
