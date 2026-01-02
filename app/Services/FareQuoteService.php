<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FareQuoteService
{
    private const TENANT_FALLBACK_ID = 100;

    /**
     * Calcula un quote para un tenant + puntos (origen/destino + paradas).
     *
     * @return array{amount:int,distance_m:int,duration_s:int,stops_n:int,policy_source?:string}
     */
    public function quoteForTenantAndPoints(
        int   $tenantId,
        array $origin,
        array $destination,
        array $stops = [],
        ?float $roundToStep = null,
    ): array {

        // 0) TZ del tenant (para regla de noche)
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone')
            ?: config('app.timezone', 'UTC');

        // 1) Resolver policy con fallback en cascada
        [$pol, $source] = $this->resolvePolicyWithFallback($tenantId);

        // 2) Defaults “duros” (último recurso)
        $defaults = [
            'mode'             => 'meter',
            'base_fee'         => 35.0,
            'per_km'           => 12.0,
            'per_min'          => 2.0,
            'min_total'        => 50.0,
            'night_multiplier' => 1.20,
            'night_start_hour' => 22,
            'night_end_hour'   => 6,
            'round_mode'       => 'step',
            'round_step'       => 1.00,
            'round_decimals'   => 0,
            'stop_fee'         => 0.0,
        ];

        // 3) Mapear policy a variables (con fallback de defaults por campo)
        $baseFee    = (float)($pol->base_fee         ?? $defaults['base_fee']);
        $perKm      = (float)($pol->per_km           ?? $defaults['per_km']);
        $perMin     = (float)($pol->per_min          ?? $defaults['per_min']);
        $minTotal   = (float)($pol->min_total        ?? $defaults['min_total']);
        $nightMul   = (float)($pol->night_multiplier ?? $defaults['night_multiplier']);
        $nightStart = (int)  ($pol->night_start_hour ?? $defaults['night_start_hour']);
        $nightEnd   = (int)  ($pol->night_end_hour   ?? $defaults['night_end_hour']);
        $roundMode  = (string)($pol->round_mode      ?? $defaults['round_mode']);     // step|decimals
        $roundStep  = (float) ($pol->round_step      ?? $defaults['round_step']);
        $roundDec   = (int)   ($pol->round_decimals  ?? $defaults['round_decimals']);
        $stopFee    = (float)($pol->stop_fee         ?? $defaults['stop_fee']);

        // 4) Anti-cero: si la policy existe pero core está en 0, aplicar fallback duro
        $coreAllZero = ($baseFee <= 0.0 && $perKm <= 0.0 && $perMin <= 0.0);
        if ($coreAllZero) {
            Log::warning('FareQuote.policy_all_zero_using_defaults', [
                'tenant_id' => $tenantId,
                'source'    => $source,
            ]);
            $baseFee  = (float)$defaults['base_fee'];
            $perKm    = (float)$defaults['per_km'];
            $perMin   = (float)$defaults['per_min'];
            $minTotal = (float)$defaults['min_total'];
            $nightMul = (float)$defaults['night_multiplier'];
            $nightStart = (int)$defaults['night_start_hour'];
            $nightEnd   = (int)$defaults['night_end_hour'];
            $roundMode  = (string)$defaults['round_mode'];
            $roundStep  = (float)$defaults['round_step'];
            $roundDec   = (int)$defaults['round_decimals'];
            $stopFee    = (float)$defaults['stop_fee'];
            $source     = 'hard_defaults';
        }

        // Si la app manda un step específico (ej. 5 pesos), lo respetamos por encima del policy
        if ($roundToStep !== null) {
            $roundMode = 'step';
            $roundStep = $roundToStep;
        }

        // --- Normalizar puntos: origen → paradas (máx 2) → destino ---
        $points = [];
        $points[] = [(float)($origin['lat'] ?? 0), (float)($origin['lng'] ?? 0)];

        $cleanStops = [];
        foreach ($stops as $s) {
            if (!isset($s['lat'], $s['lng'])) continue;
            $cleanStops[] = ['lat' => (float)$s['lat'], 'lng' => (float)$s['lng']];
            $points[] = [$cleanStops[\count($cleanStops)-1]['lat'], $cleanStops[\count($cleanStops)-1]['lng']];
            if (\count($cleanStops) >= 2) break;
        }

        $points[] = [(float)($destination['lat'] ?? 0), (float)($destination['lng'] ?? 0)];

        if (\count($points) < 2) {
            return ['amount'=>0,'distance_m'=>0,'duration_s'=>0,'stops_n'=>0,'policy_source'=>$source];
        }

        // --- Distancia Haversine por tramos + 25% red vial ---
        $toRad = static fn (float $d): float => $d * M_PI / 180;
        $R = 6371000;

        $distStraight = 0.0;
        for ($i=0; $i < \count($points)-1; $i++) {
            [$A_lat,$A_lng] = $points[$i];
            [$B_lat,$B_lng] = $points[$i+1];

            $dLat = $toRad($B_lat - $A_lat);
            $dLng = $toRad($B_lng - $A_lng);

            $a = \sin($dLat/2) ** 2
               + \cos($toRad($A_lat)) * \cos($toRad($B_lat)) * \sin($dLng/2) ** 2;

            $c = 2 * \asin(\min(1, \sqrt($a)));
            $distStraight += $R * $c;
        }

        $distM = (int)\round($distStraight * 1.25);
        $speedMps = 24000 / 3600; // 24 km/h
        $durS = (int)\max(180, \round($distM / \max(1e-6, $speedMps)));

        // --- Tarifa base + km + min + fee por paradas ---
        $km  = $distM / 1000.0;
        $min = $durS / 60.0;

        $amount = $baseFee + ($km * $perKm) + ($min * $perMin) + ($stopFee * \count($cleanStops));

        // --- Multiplicador nocturno (en TZ del tenant) ---
        $nowH = (int) now($tz)->format('H');
        $isNight = ($nightStart <= $nightEnd)
            ? ($nowH >= $nightStart && $nowH < $nightEnd)
            : ($nowH >= $nightStart || $nowH < $nightEnd);

        if ($isNight && $nightMul > 0) {
            $amount *= $nightMul;
        }

        if ($minTotal > 0 && $amount < $minTotal) {
            $amount = $minTotal;
        }

        // --- Redondeo ---
        if ($roundMode === 'decimals') {
            $amount = \round($amount, \max(0, $roundDec));
        } else {
            $step = $roundStep > 0 ? $roundStep : 1.0;
            $amount = \round($amount / $step) * $step;
        }

        $amount = (int)\round($amount);

        return [
            'amount'        => $amount,
            'distance_m'    => $distM,
            'duration_s'    => $durS,
            'stops_n'       => \count($cleanStops),
            'policy_source' => $source, // útil para debug en QA
        ];
    }

    /**
     * Retorna [policy, source] donde source ∈ tenant|fallback_100|hard_defaults.
     * Opcionalmente auto-provisiona una policy para el tenant si no existe.
     */
    private function resolvePolicyWithFallback(int $tenantId): array
    {
        // a) policy del tenant
        $pol = DB::table('tenant_fare_policies')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        if ($pol) return [$pol, 'tenant'];

        // b) policy del tenant 100 (global)
        $fallback = DB::table('tenant_fare_policies')
            ->where('tenant_id', self::TENANT_FALLBACK_ID)
            ->orderByDesc('id')
            ->first();

        if ($fallback) {
            // Opcional: autoprovisionar para el tenant (descomenta si quieres)
            // $this->provisionPolicyFromFallback($tenantId, $fallback);

            return [$fallback, 'fallback_100'];
        }

        // c) sin nada
        return [null, 'hard_defaults'];
    }

    /**
     * Crea una policy para el tenant clonando la del fallback.
     * Útil para demo: el tenant entra a editar y ya ve valores.
     */
    private function provisionPolicyFromFallback(int $tenantId, object $fallback): void
    {
        try {
            DB::table('tenant_fare_policies')->insert([
                'tenant_id'         => $tenantId,
                'mode'              => $fallback->mode ?? 'meter',
                'base_fee'          => $fallback->base_fee ?? 35,
                'per_km'            => $fallback->per_km ?? 12,
                'per_min'           => $fallback->per_min ?? 2,
                'night_start_hour'  => $fallback->night_start_hour ?? 22,
                'night_end_hour'    => $fallback->night_end_hour ?? 6,
                'round_mode'        => $fallback->round_mode ?? 'step',
                'round_decimals'    => $fallback->round_decimals ?? 0,
                'round_step'        => $fallback->round_step ?? 1.0,
                'night_multiplier'  => $fallback->night_multiplier ?? 1.2,
                'round_to'          => $fallback->round_to ?? 1.0,
                'min_total'         => $fallback->min_total ?? 50,
                'extras'            => $fallback->extras ?? json_encode([]),
                'stop_fee'          => $fallback->stop_fee ?? 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            Log::info('FareQuote.provisioned_policy_from_fallback', [
                'tenant_id' => $tenantId,
                'from'      => self::TENANT_FALLBACK_ID,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FareQuote.provision_failed', [
                'tenant_id' => $tenantId,
                'err'       => $e->getMessage(),
            ]);
        }
    }
}
