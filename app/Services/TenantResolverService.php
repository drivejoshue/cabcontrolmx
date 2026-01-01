<?php

namespace App\Services;

use App\Models\Tenant;

class TenantResolverService
{
    /**
     * Resuelve el tenant (central) al que pertenece este punto de pickup.
     * - Usa latitud/longitud del tenant como centro de la ciudad
     * - Usa coverage_radius_km como radio
     * - Solo considera tenants con allow_marketplace = 1
     */
    public function resolveForPickupPoint(float $lat, float $lng): ?Tenant
    {
        // Tenants que aceptan pasajeros desde la app
        $tenants = Tenant::query()
            ->where('allow_marketplace', 1)
            ->where('public_active', 1)   
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->get();

        if ($tenants->isEmpty()) {
            return null;
        }

        // Calcular distancia y filtrar por radio
            $candidates = $tenants->map(function (Tenant $t) use ($lat, $lng) {
            $distKm = $this->haversineDistanceKm(
                $lat,
                $lng,
                (float) $t->latitud,
                (float) $t->longitud,
            );

            // Radio efectivo: si está null, toma un default (ej. 30 km)
            $radiusKm = $t->coverage_radius_km ?: 30.0;

            // Guardamos en propiedades "virtuales" para ordenar/filtrar
            $t->distance_km        = $distKm;
            $t->effective_radius_km = $radiusKm;

            return $t;
        })->filter(function (Tenant $t) {
            // Solo los que están dentro del radio de cobertura
            return $t->distance_km <= $t->effective_radius_km;
        });

        if ($candidates->isEmpty()) {
            return null; // Fuera de cobertura
        }

        // De los que aplican, el más cercano
        return $candidates->sortBy('distance_km')->first();
    }

    private function haversineDistanceKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
