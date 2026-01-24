<?php
// app/Services/DispatchSettingsService.php
namespace App\Services;

use App\Models\DispatchSetting;
use Illuminate\Support\Facades\Cache;

final class DispatchSettingsService
{
    private const VER = 3;            // sube versión por cambio de comportamiento/cache-key
    private const CACHE_TTL = 15;     // segundos
    private const CORE_TENANT_ID = 100;

    /**
     * Lee settings del tenant actual; fallback a 100 solo si un campo viene vacío (NULL / '').
     */
    public static function forTenant(int $tenantId): object
    {
        $requestedTenantId = (int) $tenantId;

        // Cache por tenant solicitado (no por 100)
        $key = "dispatch:settings:v" . self::VER . ":tenant:{$requestedTenantId}";

        return Cache::remember($key, self::CACHE_TTL, function () use ($requestedTenantId) {

            $rowTenant = DispatchSetting::query()
                ->where('tenant_id', $requestedTenantId)
                ->orderByDesc('id')
                ->first();

            $rowCore = null;
            if ($requestedTenantId !== self::CORE_TENANT_ID) {
                $rowCore = DispatchSetting::query()
                    ->where('tenant_id', self::CORE_TENANT_ID)
                    ->orderByDesc('id')
                    ->first();
            }

            // Defaults consistentes (último fallback)
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

            // Vacío = NULL o '' (NO trata 0 como vacío)
            $isEmpty = static function ($v): bool {
                return $v === null || (is_string($v) && trim($v) === '');
            };

            // Valor por campo: tenant -> core(100) -> default
            $pick = static function (string $k) use ($rowTenant, $rowCore, $d, $isEmpty) {
                if ($rowTenant && isset($rowTenant->$k) && !$isEmpty($rowTenant->$k)) return $rowTenant->$k;
                if ($rowCore   && isset($rowCore->$k)   && !$isEmpty($rowCore->$k))   return $rowCore->$k;
                return $d[$k] ?? null;
            };

            $src = [
                'auto_dispatch_radius_km' => $pick('auto_dispatch_radius_km'),
                'nearby_search_radius_km' => $pick('nearby_search_radius_km'),
                'stand_radius_km'         => $pick('stand_radius_km'),
                'offer_expires_sec'       => $pick('offer_expires_sec'),
                'wave_size_n'             => $pick('wave_size_n'),
                'lead_time_min'           => $pick('lead_time_min'),
                'use_google_for_eta'      => $pick('use_google_for_eta'),
                'allow_fare_bidding'      => $pick('allow_fare_bidding'),
                'extras'                  => $pick('extras'),
                'auto_enabled'            => $pick('auto_enabled'),
                'auto_delay_sec'          => $pick('auto_delay_sec'),
                'auto_assign_if_single'   => $pick('auto_assign_if_single'),
                'auto_dispatch_delay_s'   => $pick('auto_dispatch_delay_s'),
                'auto_dispatch_preview_n' => $pick('auto_dispatch_preview_n'),
                'max_queue'               => $pick('max_queue'),
                'queue_sla_minutes'       => $pick('queue_sla_minutes'),
                'central_priority'        => $pick('central_priority'),
                'availability_min_ratio'  => $pick('availability_min_ratio'),
            ];

            // Normalizador (mismo contrato que ya usabas)
            return (object) [
                'requested_tenant_id' => $requestedTenantId,
                'fallback_tenant_id'  => self::CORE_TENANT_ID,

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
        });
    }

    public static function forgetTenant(int $tenantId): void
    {
        $tenantId = (int) $tenantId;
        Cache::forget("dispatch:settings:v" . self::VER . ":tenant:{$tenantId}");
    }
}
