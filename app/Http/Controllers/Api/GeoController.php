<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoController
{
    private function assertValidPoint(array $p, string $name): void
    {
        $lat = (float)$p['lat'];
        $lng = (float)$p['lng'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            abort(422, "{$name} fuera de rango.");
        }
        if (abs($lat) < 1e-7 && abs($lng) < 1e-7) {
            abort(422, "{$name} inválido (0,0).");
        }
    }

    public function route(Request $req)
    {
        $v = $req->validate([
            'from.lat' => 'required|numeric|between:-90,90',
            'from.lng' => 'required|numeric|between:-180,180',
            'to.lat'   => 'required|numeric|between:-90,90',
            'to.lng'   => 'required|numeric|between:-180,180',
            'mode'     => 'nullable|in:driving,walking,bicycling',
            'stops'        => 'sometimes|array|max:2',
            'stops.*.lat'  => 'required|numeric|between:-90,90',
            'stops.*.lng'  => 'required|numeric|between:-180,180',
        ]);

        $from  = ['lat' => (float)$v['from']['lat'], 'lng' => (float)$v['from']['lng']];
        $to    = ['lat' => (float)$v['to']['lat'],   'lng' => (float)$v['to']['lng']];
        $mode  = $v['mode'] ?? 'driving';

        $stops = array_map(
            fn($s) => ['lat' => (float)$s['lat'], 'lng' => (float)$s['lng']],
            $v['stops'] ?? []
        );

        $this->assertValidPoint($from, 'Origen');
        $this->assertValidPoint($to, 'Destino');
        foreach ($stops as $i => $s) $this->assertValidPoint($s, "Parada S" . ($i + 1));

        Log::debug('GEO.route request', [
            'from' => $from, 'to' => $to, 'stops' => $stops, 'mode' => $mode,
            'tenant' => $req->header('X-Tenant-ID'), 'path' => $req->path(),
        ]);

        // ---------------- 1) OSRM primero (por costo) ----------------
        try {
            $seq = array_merge([$from], $stops, [$to]);
            $coords = collect($seq)
                ->map(fn($p) => sprintf('%f,%f', $p['lng'], $p['lat'])) // lng,lat
                ->implode(';');

            $url = "https://router.project-osrm.org/route/v1/driving/{$coords}?overview=full&geometries=polyline";
            $resp = Http::timeout(10)->get($url);

            if ($resp->ok()) {
                $d = $resp->json();
                if (($d['code'] ?? '') === 'Ok') {
                    $route = $d['routes'][0] ?? null;
                    $poly = $route['geometry'] ?? null;

                    if (!empty($poly)) {
                        return response()->json([
                            'ok'         => true,
                            'provider'   => 'osrm',
                            'distance_m' => (int)($route['distance'] ?? 0),
                            'duration_s' => (int)($route['duration'] ?? 0),
                            'polyline'   => $poly,
                        ]);
                    }

                    Log::warning('GEO.osrm Ok but missing geometry', compact('from','to','stops'));
                } else {
                    Log::info('GEO.osrm not Ok', ['code'=>$d['code'] ?? null] + compact('from','to','stops'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GEO.osrm exception', ['err' => $e->getMessage()]);
        }

        // ---------------- 2) Google fallback ----------------
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
                    $params['waypoints'] = implode('|', array_map(
                        fn($s) => "via:{$s['lat']},{$s['lng']}",
                        $stops
                    ));
                }

                $resp = Http::timeout(10)->get(
                    'https://maps.googleapis.com/maps/api/directions/json',
                    $params
                );

                if ($resp->ok()) {
                    $data = $resp->json();
                    if (($data['status'] ?? '') === 'OK') {
                        $route = $data['routes'][0] ?? null;
                        $legs  = $route['legs'] ?? [];

                        $distance = 0; $duration = 0;
                        foreach ($legs as $leg) {
                            $distance += (int)($leg['distance']['value'] ?? 0);
                            $duration += (int)($leg['duration']['value'] ?? 0);
                        }

                        $poly = $route['overview_polyline']['points'] ?? null;

                        if (!empty($poly)) {
                            return response()->json([
                                'ok'         => true,
                                'provider'   => 'google',
                                'distance_m' => $distance,
                                'duration_s' => $duration,
                                'polyline'   => $poly,
                            ]);
                        }

                        Log::warning('GEO.google OK but missing polyline', compact('from','to','stops'));
                    } else {
                        Log::info('GEO.google not OK', [
                            'status' => $data['status'] ?? null,
                            'error_message' => $data['error_message'] ?? null,
                        ] + compact('from','to','stops'));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('GEO.google exception', ['err' => $e->getMessage()]);
            }
        }

        // ---------------- 3) Fallback línea recta ----------------
        $seq = array_merge([$from], $stops, [$to]);
        $seq = array_values(array_filter($seq, fn($p) =>
            !(abs((float)$p['lat']) < 1e-7 && abs((float)$p['lng']) < 1e-7)
        ));

        if (count($seq) < 2) $seq = [$from, $to];

        $points = array_map(fn($p) => [(float)$p['lat'], (float)$p['lng']], $seq);

        return response()->json([
            'ok'         => true,
            'provider'   => 'fallback',
            'distance_m' => null,
            'duration_s' => null,
            'points'     => $points,
        ]);
    }
}
