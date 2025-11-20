<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FareQuoteService
{
    /**
     * Calcula un quote para un tenant + puntos (origen/destino + paradas).
     *
     * @param  int   $tenantId
     * @param  array $origin      ['lat'=>float,'lng'=>float]
     * @param  array $destination ['lat'=>float,'lng'=>float]
     * @param  array $stops       array de ['lat'=>float,'lng'=>float] (máx 2)
     * @param  float|null $roundToStep  si viene, fuerza redondeo por step (5, 10, etc)
     * @return array{amount:int,distance_m:int,duration_s:int,stops_n:int}
     */
    public function quoteForTenantAndPoints(
        int   $tenantId,
        array $origin,
        array $destination,
        array $stops = [],
        ?float $roundToStep = null,
    ): array {
        // --- 1) Política del tenant (misma tabla tenant_fare_policies) ---
        $pol = DB::table('tenant_fare_policies')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        // Defaults razonables si no hay política
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
        $stopFee    = (float)($pol->stop_fee         ?? 0.0);

        // Si la app manda un step específico (ej. 5 pesos), lo respetamos por encima del policy
        if ($roundToStep !== null) {
            $roundMode = 'step';
            $roundStep = $roundToStep;
        }

        // --- 2) Normalizar puntos: origen → paradas (máx 2) → destino ---
        $points = [];
        $points[] = [
            (float)($origin['lat'] ?? 0),
            (float)($origin['lng'] ?? 0),
        ];

        $cleanStops = [];
        foreach ($stops as $s) {
            if (!isset($s['lat'], $s['lng'])) {
                continue;
            }
            $cleanStops[] = [
                'lat' => (float)$s['lat'],
                'lng' => (float)$s['lng'],
            ];
            $points[] = [$cleanStops[\count($cleanStops) - 1]['lat'], $cleanStops[\count($cleanStops) - 1]['lng']];

            if (\count($cleanStops) >= 2) {
                break; // máx 2 paradas
            }
        }

        $points[] = [
            (float)($destination['lat'] ?? 0),
            (float)($destination['lng'] ?? 0),
        ];

        if (\count($points) < 2) {
            // No hay suficiente info para ruta
            return [
                'amount'     => 0,
                'distance_m' => 0,
                'duration_s' => 0,
                'stops_n'    => 0,
            ];
        }

        // --- 3) Distancia con Haversine por tramos + 25% red vial (como en Dispatch::quote) ---
        $toRad = static fn (float $d): float => $d * M_PI / 180;
        $R = 6371000; // metros

        $distStraight = 0.0;
        for ($i = 0; $i < \count($points) - 1; $i++) {
            [$A_lat, $A_lng] = $points[$i];
            [$B_lat, $B_lng] = $points[$i + 1];

            $dLat = $toRad($B_lat - $A_lat);
            $dLng = $toRad($B_lng - $A_lng);

            $a = \sin($dLat / 2) ** 2
               + \cos($toRad($A_lat)) * \cos($toRad($B_lat)) * \sin($dLng / 2) ** 2;

            $c = 2 * \asin(\min(1, \sqrt($a)));
            $distStraight += $R * $c;
        }

        $distM = (int)\round($distStraight * 1.25); // +25% por red vial
        $speedMps = 24000 / 3600; // 24 km/h
        $durS = (int)\max(180, \round($distM / \max(1e-6, $speedMps)));

        // --- 4) Tarifa base + km + min + fee por paradas ---
        $km  = $distM / 1000.0;
        $min = $durS / 60.0;

        $amount = $baseFee
                + ($km  * $perKm)
                + ($min * $perMin)
                + ($stopFee * \count($cleanStops));

        // --- 5) Multiplicador nocturno flexible (usa night_start_hour / night_end_hour) ---
        $nowH = (int)now()->format('H');
        $isNight = ($nightStart <= $nightEnd)
            ? ($nowH >= $nightStart && $nowH < $nightEnd)
            : ($nowH >= $nightStart || $nowH < $nightEnd); // ventana cruzando medianoche

        if ($isNight && $nightMul > 0) {
            $amount *= $nightMul;
        }

        // Mínimo por viaje
        if ($minTotal > 0 && $amount < $minTotal) {
            $amount = $minTotal;
        }

        // --- 6) Redondeo según política (o roundToStep) ---
        if ($roundMode === 'decimals') {
            $amount = \round($amount, \max(0, $roundDec));
        } else {
            $step = $roundStep > 0 ? $roundStep : 1.0;
            $amount = \round($amount / $step) * $step;
        }

        // Mantener compatibilidad: devolver entero (pesos)
        $amount = (int)\round($amount);

        return [
            'amount'     => $amount,
            'distance_m' => $distM,
            'duration_s' => $durS,
            'stops_n'    => \count($cleanStops),
        ];
    }
}
