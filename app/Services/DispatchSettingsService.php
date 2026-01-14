<?php
// app/Services/DispatchSettingsService.php
namespace App\Services;

use App\Models\DispatchSetting;
use Illuminate\Support\Facades\Cache;

final class DispatchSettingsService
{
    private const VER = 2;            // súbela porque cambia el comportamiento/cache-key
    private const CACHE_TTL = 15;     // segundos
    private const CORE_TENANT_ID = 100;

    /**
     * Fuente única de verdad de settings.
     * HOY: autodespacho global => siempre toma settings del tenant 100 (Orbana Core).
     * Mañana: si vuelves a individual, solo cambias effectiveTenantId().
     */
    public static function forTenant(int $tenantId): object
    {
        $requestedTenantId = (int)$tenantId;
        $effectiveTenantId = self::effectiveTenantId($requestedTenantId);

        $key = "dispatch:settings:v".self::VER.":tenant:{$effectiveTenantId}";

        return Cache::remember($key, self::CACHE_TTL, function () use ($requestedTenantId, $effectiveTenantId) {
            $row = DispatchSetting::query()
                ->where('tenant_id', $effectiveTenantId)
                ->orderByDesc('id')
                ->first();

            // Defaults consistentes
            $d = [
                'auto_dispatch_radius_km' => 4.00,
                'nearby_search_radius_km' => 4.00,
                'stand_radius_km'         => 3.00,
                'offer_expires_sec'       => 300,
                'wave_size_n'             => 12,
                'lead_time_min'           => 15,
                'use_google_for_eta'      => true,
                'allow_fare_bidding'      => false,
                'extras'                  => null,
                'auto_enabled'            => true,
                'auto_delay_sec'          => 0,
                'auto_assign_if_single'   => false,
                'auto_dispatch_delay_s'   => null,
                'auto_dispatch_preview_n' => 8,
                'max_queue'               => 2,
                'queue_sla_minutes'       => 20,
                'central_priority'        => true,
                'availability_min_ratio'  => null,
            ];

            // Helper normalizador
            $build = function(array $src) use ($requestedTenantId, $effectiveTenantId) {
                return (object) [
                    // debug informativo (no rompe contrato; si no lo quieres, bórralo)
                    'requested_tenant_id' => $requestedTenantId,
                    'effective_tenant_id' => $effectiveTenantId,

                    // aliases “bonitos”
                    'enabled'               => (bool)  $src['auto_enabled'],
                    'delay_s'               => (int)   (($src['auto_dispatch_delay_s'] ?? null) ?? $src['auto_delay_sec']),
                    'radius_km'             => (float) $src['auto_dispatch_radius_km'],
                    'nearby_radius_km'      => (float) $src['nearby_search_radius_km'],
                    'stand_radius_km'       => (float) $src['stand_radius_km'],
                    'limit_n'               => (int)   (($src['auto_dispatch_preview_n'] ?? null) ?? $src['wave_size_n']),
                    'preview_n'             => (int)   (($src['auto_dispatch_preview_n'] ?? null) ?? $src['wave_size_n']),
                    'expires_s'             => (int)   $src['offer_expires_sec'],
                    'lead_time_min'         => (int)   $src['lead_time_min'],
                    'use_google_for_eta'    => (bool)  $src['use_google_for_eta'],
                    'allow_fare_bidding'    => (bool)  $src['allow_fare_bidding'],
                    'auto_assign_if_single' => (bool)  $src['auto_assign_if_single'],
                    'max_queue'             => (int)   $src['max_queue'],
                    'queue_sla_minutes'     => (int)   $src['queue_sla_minutes'],
                    'central_priority'      => (bool)  $src['central_priority'],
                    'availability_min_ratio'=> $src['availability_min_ratio'] !== null ? (float)$src['availability_min_ratio'] : null,
                    'extras'                => $src['extras'],
                ];
            };

            // Si no hay fila, defaults
            if (!$row) {
                return $build($d);
            }

            // Normalización usando la fila real + defaults
            $val = fn($k) => $row->$k ?? $d[$k];

            $src = [
                'auto_dispatch_radius_km' => $val('auto_dispatch_radius_km'),
                'nearby_search_radius_km' => $val('nearby_search_radius_km'),
                'stand_radius_km'         => $val('stand_radius_km'),
                'offer_expires_sec'       => $val('offer_expires_sec'),
                'wave_size_n'             => $val('wave_size_n'),
                'lead_time_min'           => $val('lead_time_min'),
                'use_google_for_eta'      => $val('use_google_for_eta'),
                'allow_fare_bidding'      => $val('allow_fare_bidding'),
                'extras'                  => $val('extras'),
                'auto_enabled'            => $val('auto_enabled'),
                'auto_delay_sec'          => $val('auto_delay_sec'),
                'auto_assign_if_single'   => $val('auto_assign_if_single'),
                'auto_dispatch_delay_s'   => $val('auto_dispatch_delay_s'),
                'auto_dispatch_preview_n' => $val('auto_dispatch_preview_n'),
                'max_queue'               => $val('max_queue'),
                'queue_sla_minutes'       => $val('queue_sla_minutes'),
                'central_priority'        => $val('central_priority'),
                'availability_min_ratio'  => $val('availability_min_ratio'),
            ];

            return $build($src);
        });
    }

    /**
     * HOY: siempre 100 (Orbana Core).
     * Mañana: return $requestedTenantId; para volver a individual.
     */
    private static function effectiveTenantId(int $requestedTenantId): int
    {
        return self::CORE_TENANT_ID;
    }

    /**
     * (Opcional) si ya lo usas en otros lados: invalidación de cache.
     */
    public static function forgetTenant(int $tenantId): void
    {
        $effectiveTenantId = self::effectiveTenantId((int)$tenantId);
        $key = "dispatch:settings:v".self::VER.":tenant:{$effectiveTenantId}";
        Cache::forget($key);
    }
}
