<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoController
{
    private function isZeroPoint(array $p): bool
    {
        $lat = (float)($p['lat'] ?? 0);
        $lng = (float)($p['lng'] ?? 0);
        return abs($lat) < 1e-7 && abs($lng) < 1e-7;
    }

    private function assertValidPoint(array $p, string $name): void
    {
        $lat = (float)$p['lat'];
        $lng = (float)$p['lng'];

        // rango real
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            abort(422, "{$name} fuera de rango.");
        }

        // evita África (0,0) y puntos no inicializados
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

            // stops opcional; si viene, valida cada item
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

        // --- Guard rails (si algo viene mal, corta con 422 y listo) ---
        $this->assertValidPoint($from, 'Origen');
        $this->assertValidPoint($to, 'Destino');
        foreach ($stops as $i => $s) {
            // stop = 0,0 es muy común cuando el móvil no lo setea
            $this->assertValidPoint($s, "Parada S" . ($i + 1));
        }

        // Log útil para detectar el bug real (si te vuelve a pasar)
        Log::debug('GEO.route request', [
            'from' => $from,
            'to' => $to,
            'stops' => $stops,
            'mode' => $mode,
            'tenant' => $req->header('X-Tenant-ID'),
            'path' => $req->path(),
        ]);

        // ---------- 1) Google Directions ----------
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

                        Log::warning('GEO.google OK but missing polyline', [
                            'from'=>$from,'to'=>$to,'stops'=>$stops
                        ]);
                    } else {
                        Log::info('GEO.google not OK', [
                            'status' => $data['status'] ?? null,
                            'error_message' => $data['error_message'] ?? null,
                            'from'=>$from,'to'=>$to,'stops'=>$stops
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('GEO.google exception', ['err' => $e->getMessage()]);
            }
        }

        // ---------- 2) OSRM multipunto ----------
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

                    Log::warning('GEO.osrm Ok but missing geometry', [
                        'from'=>$from,'to'=>$to,'stops'=>$stops
                    ]);
                } else {
                    Log::info('GEO.osrm not Ok', [
                        'code' => $d['code'] ?? null,
                        'from'=>$from,'to'=>$to,'stops'=>$stops
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GEO.osrm exception', ['err' => $e->getMessage()]);
        }

        // ---------- 3) Fallback: línea recta O->S...->D ----------
        // (extra safety: nunca regreses puntos 0,0 aunque alguien te los mande)
        $seq = array_merge([$from], $stops, [$to]);

        $seq = array_values(array_filter($seq, function ($p) {
            return !(abs((float)$p['lat']) < 1e-7 && abs((float)$p['lng']) < 1e-7);
        }));

        // si por alguna razón se quedara sin puntos suficientes, fuerza from->to
        if (count($seq) < 2) {
            $seq = [$from, $to];
        }

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
