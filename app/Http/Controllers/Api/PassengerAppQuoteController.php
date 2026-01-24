<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Services\FareQuoteService;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;
use App\Models\CityPlace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PassengerAppQuoteController extends Controller
{
    public function quote(
        Request $req,
        TenantResolverService $tenantResolver,
        FareQuoteService $fareQuote,
    ) {
        $v = $req->validate([
            'origin_lat'    => 'required|numeric',
            'origin_lng'    => 'required|numeric',
            'dest_lat'      => 'required|numeric',
            'dest_lng'      => 'required|numeric',
            'round_to_step' => 'nullable|numeric',
            'dest_city_place_id' => 'nullable|integer',
            'stops'         => 'nullable|array|max:2',
            'stops.*.lat'   => 'required_with:stops|numeric',
            'stops.*.lng'   => 'required_with:stops|numeric',

            'firebase_uid'  => 'nullable|string|max:128',
        ]);

        // 1) Resolver tenant por punto de recogida
        $tenant = $tenantResolver->resolveForPickupPoint(
            (float) $v['origin_lat'],
            (float) $v['origin_lng'],
        );

        if (! $tenant) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'no_coverage',
                    'message' => 'No hay cobertura en tu zona. No hay centrales disponibles por ahora.',
                ], 422);
            }

        // 2) Opcional: localizar pasajero por firebase_uid
        $passenger = null;
        if (! empty($v['firebase_uid'])) {
            $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        }

        $origin = [
            'lat' => (float) $v['origin_lat'],
            'lng' => (float) $v['origin_lng'],
        ];

        $destination = [
            'lat' => (float) $v['dest_lat'],
            'lng' => (float) $v['dest_lng'],
        ];

        $stops = [];
        if (! empty($v['stops'])) {
            foreach ($v['stops'] as $s) {
                $stops[] = [
                    'lat' => (float) $s['lat'],
                    'lng' => (float) $s['lng'],
                ];
            }
        }

        $roundToStep = $req->has('round_to_step')
            ? (float) $req->input('round_to_step')
            : null;

        // 3) Llamar servicio de tarifa
        $res = $fareQuote->quoteForTenantAndPoints(
            tenantId:    (int) $tenant->id,
            origin:      $origin,
            destination: $destination,
            stops:       $stops,
            roundToStep: $roundToStep,
        );

        // En tu log se ve que $res trae:
        // ['amount' => 105, 'distance_m' => 8286, 'duration_s' => 1243, 'stops_n' => 0]

        $baseAmount = (int) round($res['amount'] ?? 0);

        if ($baseAmount <= 0) {
            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo calcular la tarifa.',
            ], 500);
        }

        // Calculamos rango amigable para el slider (-20 +20 %)
       // Calculamos rango amigable para el slider (-20 +20 %)
        // ✅ Slider desde policy (si no viene, fallback 0.80 / 1.20)
        $sliderMinPct = isset($res['slider_min_pct']) ? (float)$res['slider_min_pct'] : 0.80;
        $sliderMaxPct = isset($res['slider_max_pct']) ? (float)$res['slider_max_pct'] : 1.20;

        // clamp defensivo
        $sliderMinPct = max(0.50, min(1.00, $sliderMinPct));
        $sliderMaxPct = max(1.00, min(2.00, $sliderMaxPct));
        if ($sliderMaxPct <= $sliderMinPct) {
            $sliderMinPct = 0.80;
            $sliderMaxPct = 1.20;
        }

        $recommended = $baseAmount;
        $minFare = (int) max(1, floor($recommended * $sliderMinPct));
        $maxFare = (int) max($minFare + 1, ceil($recommended * $sliderMaxPct));


        // ✅ CityId para reglas por lugar (aeropuerto, terminal, etc.)
            $cityId = $this->resolveCityIdForQuote($tenant, $origin);

            // ✅ Regla por CityPlace (si aplica, puede modificar el recomendado)
            [$finalRecommended, $fareTag] = $this->applyPlaceFareRule(
                $cityId,
                $origin,
                $destination,
                $v['dest_city_place_id'] ?? null,
                $recommended
            );

            if ((int)$finalRecommended !== (int)$recommended) {
                $recommended = (int)$finalRecommended;

                // Recalcular min/max con el mismo pct (consistente con el slider)
                $minFare = (int) max(1, floor($recommended * $sliderMinPct));
                $maxFare = (int) max($minFare + 1, ceil($recommended * $sliderMaxPct));
            }


        return response()->json([
            'ok'             => true,
            'tenant_id'      => (int) $tenant->id,
            'passenger_id'   => $passenger?->id,

            // 👇 Lo que espera la app Kotlin
            'recommended_fare' => $recommended,
            'min_fare'         => $minFare,
            'max_fare'         => $maxFare,

            // Datos extra útiles
            'distance_m'     => isset($res['distance_m']) ? (int) $res['distance_m'] : null,
            'duration_s'     => isset($res['duration_s']) ? (int) $res['duration_s'] : null,
            'stops_n'        => $res['stops_n'] ?? null,

            // Compat: por si luego quieres ver/usar amount crudo
            'amount'         => $baseAmount,

            'fare_tag' => $fareTag,
            'dest_city_place_id' => $fareTag['place_id'] ?? ($v['dest_city_place_id'] ?? null),

        ]);
    }



private function resolveCityIdForQuote($tenant, array $origin): ?int
{
    // Preferencia: si tu tenant tiene city_id, úsalo.
    if (isset($tenant->city_id) && $tenant->city_id) {
        return (int) $tenant->city_id;
    }

    $lat = (float)($origin['lat'] ?? 0);
    $lng = (float)($origin['lng'] ?? 0);
    if (!$lat || !$lng) return null;

    // Resolver por cities: dentro de radius_km (elige la ciudad más cercana al centro)
    $cities = DB::table('cities')
        ->where('is_active', 1)
        ->get(['id','center_lat','center_lng','radius_km']);

    $bestId = null;
    $bestD = null;

    foreach ($cities as $c) {
        $dKm = $this->haversineMeters(
            $lat, $lng,
            (float)$c->center_lat, (float)$c->center_lng
        ) / 1000.0;

        if ($dKm > (float)$c->radius_km) continue;

        if ($bestD === null || $dKm < $bestD) {
            $bestD = $dKm;
            $bestId = (int)$c->id;
        }
    }

    return $bestId;
}


/**
 * Aplica regla de tarifa por CityPlace (v1).
 * - Trigger por DESTINO dentro de fare_radius_m (o por dest_city_place_id si viene)
 * - Tier "near": si ORIGEN dentro de fare_near_origin_radius_m
 *
 * Retorna: [newAmount, fareTag|null]
 */
private function applyPlaceFareRule(
    ?int $cityId,
    array $origin,
    array $destination,
    $destCityPlaceId,
    int $baseAmount
): array {
    if (!$cityId) return [$baseAmount, null];

    $place = null;

    // 1) Si la app manda dest_city_place_id, priorizarlo (pero validar city y activo)
    if (!empty($destCityPlaceId)) {
        $place = CityPlace::where('id', (int)$destCityPlaceId)
            ->where('city_id', $cityId)
            ->where('is_active', 1)
            ->where('fare_is_active', 1)
            ->first([
                'id','city_id','label','address','lat','lng','category','priority',
                'fare_is_active','fare_radius_m','fare_near_origin_radius_m','fare_rule'
            ]);
    }

    // 2) Si no vino id, buscar por geofencing de destino dentro del radio
    if (!$place) {
        $candidates = CityPlace::where('city_id', $cityId)
            ->where('is_active', 1)
            ->where('fare_is_active', 1)
            ->where('fare_radius_m', '>', 0)
            // v1: si quieres solo aeropuerto, descomenta:
            // ->where('category', 'airport')
            ->orderByDesc('priority')
            ->get([
                'id','label','lat','lng','category','priority',
                'fare_radius_m','fare_near_origin_radius_m','fare_rule'
            ]);

        $best = null;
        foreach ($candidates as $p) {
            $radiusM = (int)($p->fare_radius_m ?? 0);
            if ($radiusM <= 0) continue;

            $dDest = $this->haversineMeters(
                (float)$destination['lat'], (float)$destination['lng'],
                (float)$p->lat, (float)$p->lng
            );

            if ($dDest > $radiusM) continue;

            // score: prioridad alta gana; a igualdad, el más cercano al destino
            $score = ((int)$p->priority * 1_000_000) - (int)$dDest;

            if (!$best || $score > $best['score']) {
                $best = ['p' => $p, 'score' => $score, 'dDest' => $dDest];
            }
        }

        $place = $best['p'] ?? null;
    }

    if (!$place) return [$baseAmount, null];

    $rule = $this->jsonToArray($place->fare_rule);
    if (empty($rule) || empty($rule['enabled'])) return [$baseAmount, null];

    // v1: Solo aplicamos si dice destino (o no dice nada)
    $appliesTo = $rule['applies_to'] ?? 'destination';
    if (!in_array($appliesTo, ['destination','either'], true)) {
        return [$baseAmount, null];
    }

    $mode  = (string)($rule['mode'] ?? '');
    $label = (string)($rule['label'] ?? 'Tarifa especial');

    // Tier near por origen
    $nearOriginRadiusM = (int)($place->fare_near_origin_radius_m ?? 0);
    if (!empty($rule['near_origin_radius_m'])) {
        // si lo incluyes también dentro del JSON, lo puedes sobreescribir
        $nearOriginRadiusM = (int)$rule['near_origin_radius_m'];
    }

    $isNear = false;
    $dOrigin = null;
    if ($nearOriginRadiusM > 0) {
        $dOrigin = $this->haversineMeters(
            (float)$origin['lat'], (float)$origin['lng'],
            (float)$place->lat, (float)$place->lng
        );
        $isNear = ($dOrigin <= $nearOriginRadiusM);
    }

    // Aplicación por modo
    if ($mode === 'extra_fixed_tiered') {
        $full = (int)($rule['full_extra'] ?? 0);
        $near = (int)($rule['near_extra'] ?? $full);

        $extra = $isNear ? $near : $full;
        if ($extra <= 0) return [$baseAmount, null];

        $newAmount = $baseAmount + $extra;

        return [$newAmount, [
            'type'        => 'extra',
            'label'       => $label,
            'tier'        => $isNear ? 'near' : 'full',
            'amount'      => $extra,
            'place_id'    => (int)$place->id,
            'place_label' => (string)$place->label,
            'category'    => $place->category,
            'fare_active' => (int)($place->fare_is_active ?? 1), // opcional QA
            'debug'       => [
                'd_origin_m' => $dOrigin !== null ? (int)round($dOrigin) : null,
                'near_r_m'   => $nearOriginRadiusM ?: null,
            ],
        ]];

    }

    if ($mode === 'total_fixed_tiered') {
        $full = (int)($rule['full_total'] ?? 0);
        $near = (int)($rule['near_total'] ?? $full);

        $total = $isNear ? $near : $full;
        if ($total <= 0) return [$baseAmount, null];

        return [$total, [
            'type'        => 'fixed_total',
            'label'       => $label,
            'tier'        => $isNear ? 'near' : 'full',
            'amount'      => $total,
            'place_id'    => (int)$place->id,
            'place_label' => (string)$place->label,
            'category'    => $place->category,
            'debug'       => [
                'd_origin_m' => $dOrigin !== null ? (int)round($dOrigin) : null,
                'near_r_m'   => $nearOriginRadiusM ?: null,
            ],
        ]];
    }

    // modo desconocido => no aplica
    return [$baseAmount, null];
}

private function jsonToArray($v): array
{
    if (is_array($v)) return $v;
    if (is_string($v) && $v !== '') {
        $d = json_decode($v, true);
        return is_array($d) ? $d : [];
    }
    return [];
}

private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat/2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;

    return 2 * $R * asin(min(1.0, sqrt($a)));
}

}
