<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Services\FareQuoteService;
use App\Services\TenantResolverService;
use Illuminate\Http\Request;

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
                'ok'  => false,
                'msg' => 'Fuera de zona de cobertura',
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

        // Calculamos rango amigable para el slider (-5% / +15%)
        $recommended = $baseAmount;
        $minFare = (int) max(1, floor($recommended * 0.80));  // -5%
        $maxFare = (int) max($minFare + 1, ceil($recommended * 1.20)); // +15%

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
        ]);
    }
}
