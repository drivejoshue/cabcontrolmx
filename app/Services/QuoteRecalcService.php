<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class QuoteRecalcService
{
    /**
     * Recalcula distancia, duración, polyline y (si aplica) quoted_amount de un ride
     * considerando hasta 2 paradas intermedias (stops_json) + política del tenant.
     * NO pisa quoted_amount si el ride está en modo fijo o marcado como userfixed.
     */
    public function recalcWithStops(int $rideId, int $tenantId): void
    {
        $ride = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideId)
            ->first();

        if (!$ride) return;

        // --- 1) Construir puntos O -> (stops...) -> D ---
        $stops = $ride->stops_json ? (json_decode($ride->stops_json, true) ?: []) : [];
        $stops = array_slice(array_values($stops), 0, 2); // máx 2

        $points = [];
        if ($ride->origin_lat !== null && $ride->origin_lng !== null) {
            $points[] = [(float)$ride->origin_lat, (float)$ride->origin_lng];
        }
        foreach ($stops as $s) {
            if (isset($s['lat'], $s['lng'])) {
                $points[] = [(float)$s['lat'], (float)$s['lng']];
            }
        }
        if ($ride->dest_lat !== null && $ride->dest_lng !== null) {
            $points[] = [(float)$ride->dest_lat, (float)$ride->dest_lng];
        }

        if (\count($points) < 2) return; // nada que rutear

        // --- 2) Rutear multipunto con OSRM (sin API key) ---
        $coords = collect($points)
            ->map(fn($p) => sprintf('%f,%f', $p[1], $p[0])) // lng,lat
            ->implode(';');

        $distance = null;
        $duration = null;
        $polyline = null;

        try {
            $url  = "https://router.project-osrm.org/route/v1/driving/{$coords}?overview=full&geometries=polyline";
            $resp = Http::timeout(10)->get($url);

            if ($resp->ok() && ($d = $resp->json()) && ($d['code'] ?? '') === 'Ok') {
                $route    = $d['routes'][0] ?? null;
                $distance = (int)($route['distance'] ?? 0);
                $duration = (int)($route['duration'] ?? 0);
                $polyline = $route['geometry'] ?? null;
            }
        } catch (\Throwable $e) {
            // Si OSRM falla, conservamos distancia/duración previas y solo aplicaríamos fee de paradas si corresponde
        }

        // --- 3) Política de tarifa (STOP FEE ES COLUMNA) ---
        $pol = DB::table('tenant_fare_policies')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        // Defaults si no hay fila
        $baseFee    = (float)($pol->base_fee         ?? 25);
        $perKm      = (float)($pol->per_km           ?? 8);
        $perMin     = (float)($pol->per_min          ?? 0);
        $minTotal   = (float)($pol->min_total        ?? 0);
        $nightMul   = (float)($pol->night_multiplier ?? 1.0);
        $nightStart = (int)  ($pol->night_start_hour ?? 22);
        $nightEnd   = (int)  ($pol->night_end_hour   ?? 6);
        $roundMode  = (string)($pol->round_mode      ?? 'step');     // 'step' | 'decimals'
        $roundStep  = (float) ($pol->round_step      ?? 1.00);
        $roundDec   = (int)   ($pol->round_decimals  ?? 0);

        // AHORA desde columna stop_fee (no JSON extras)
        $stopFee    = (float)($pol->stop_fee         ?? 0.0);

        // --- 4) Base de cálculo (usar nuevos si tenemos; si no, conservar actuales) ---
        $distM = $distance ?? (int)($ride->distance_m ?? 0);
        $durS  = $duration ?? (int)($ride->duration_s ?? 0);

        if ($distM <= 0 || $durS <= 0) {
            $distM = max(1000, $distM); // mínimo 1 km
            $durS  = max(180,  $durS);  // mínimo 3 min
        }

        // --- 5) Cálculo de tarifa según política + fee por paradas ---
        $km  = $distM / 1000.0;
        $min = $durS  / 60.0;
        $amt = $baseFee + ($km * $perKm) + ($min * $perMin);

        // Fee por paradas intermedias
        $amt += $stopFee * \count($stops);

        // Multiplicador nocturno
        $nowH = (int) now()->format('H');
        $isNight = ($nightStart <= $nightEnd)
            ? ($nowH >= $nightStart && $nowH < $nightEnd)
            : ($nowH >= $nightStart || $nowH < $nightEnd);

        if ($isNight && $nightMul > 0) {
            $amt *= $nightMul;
        }

        if ($minTotal > 0 && $amt < $minTotal) {
            $amt = $minTotal;
        }

        // Redondeo
        if ($roundMode === 'decimals') {
            $amt = round($amt, max(0, $roundDec));
        } else { // step
            $step = $roundStep > 0 ? $roundStep : 1.0;
            $amt  = round($amt / $step) * $step;
        }

        // --- 6) No pisar tarifa manual (fare_mode=fixed o flag userfixed/user_fixed) ---
        $fareMode     = strtolower($ride->fare_mode ?? '');
        $isFixedMode  = ($fareMode === 'fixed');

        // soporta distintos nombres posibles del flag
        $ufRaw = $ride->userfixed ?? $ride->user_fixed ?? null;
        $isUserFixed = false;
        if ($ufRaw !== null) {
            $val = is_bool($ufRaw) ? $ufRaw : strtolower((string)$ufRaw);
            $isUserFixed = ($val === true || $val === '1' || $val === 'true' || $val === 'yes' || $val === 'on');
        }

        $hasManual = $isFixedMode || $isUserFixed;

        // --- 7) Persistir cambios ---
        $updates = [
            'distance_m'     => $distM,
            'duration_s'     => $durS,
            'route_polyline' => $polyline ?: $ride->route_polyline,
            'updated_at'     => now(),
        ];

        if (!$hasManual) {
            $updates['quoted_amount'] = $amt;
        }

        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideId)
            ->update($updates);
    }
}
