<?php
// app/Services/DispatchSettingsService.php
namespace App\Services;

use App\Models\DispatchSetting;
use Illuminate\Support\Facades\Cache;

final class DispatchSettingsService
{
    // súbele si cambias el shape de salida
    private const VER = 1;
    private const CACHE_TTL = 15; // segundos

    /**
     * Fuente única de verdad de settings por tenant.
     * Devuelve un objeto tipado/normalizado con TODOS los campos útiles.
     */
    public static function forTenant(int $tenantId): object
    {
        $key = "dispatch:settings:v".self::VER.":tenant:{$tenantId}";

        return Cache::remember($key, self::CACHE_TTL, function () use ($tenantId) {
            $row = DispatchSetting::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('id')
                ->first();

            // Defaults consistentes
            $d = [
                'auto_dispatch_radius_km' => 5.00,
                'nearby_search_radius_km' => 5.00,
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

            // Si no hay fila, regresamos defaults
            if (!$row) {
                return (object) [
                    // aliases “bonitos”
                    'enabled'               => (bool) $d['auto_enabled'],
                    'delay_s'               => (int)  ($d['auto_dispatch_delay_s'] ?? $d['auto_delay_sec']),
                    'radius_km'             => (float) $d['auto_dispatch_radius_km'],
                    'nearby_radius_km'      => (float) $d['nearby_search_radius_km'],
                    'stand_radius_km'       => (float) $d['stand_radius_km'],
                    'limit_n'               => (int)   ($d['auto_dispatch_preview_n'] ?? $d['wave_size_n']),
                    'preview_n'             => (int)   ($d['auto_dispatch_preview_n'] ?? $d['wave_size_n']),
                    'expires_s'             => (int)   $d['offer_expires_sec'],
                    'lead_time_min'         => (int)   $d['lead_time_min'],
                    'use_google_for_eta'    => (bool)  $d['use_google_for_eta'],
                    'allow_fare_bidding'    => (bool)  $d['allow_fare_bidding'],
                    'auto_assign_if_single' => (bool)  $d['auto_assign_if_single'],
                    'max_queue'             => (int)   $d['max_queue'],
                    'queue_sla_minutes'     => (int)   $d['queue_sla_minutes'],
                    'central_priority'      => (bool)  $d['central_priority'],
                    'availability_min_ratio'=> $d['availability_min_ratio'] !== null ? (float)$d['availability_min_ratio'] : null,
                    'extras'                => $d['extras'],
                ];
            }

            // Normalización usando la fila real + defaults
            $val = fn($k) => $row->$k ?? $d[$k];

            return (object) [
                // aliases “bonitos”
                'enabled'               => (bool)  $val('auto_enabled'),
                'delay_s'               => (int)   ($val('auto_dispatch_delay_s') ?? $val('auto_delay_sec')),
                'radius_km'             => (float) $val('auto_dispatch_radius_km'),
                'nearby_radius_km'      => (float) $val('nearby_search_radius_km'),
                'stand_radius_km'       => (float) $val('stand_radius_km'),
                'limit_n'               => (int)   ($val('auto_dispatch_preview_n') ?? $val('wave_size_n')),
                'preview_n'             => (int)   ($val('auto_dispatch_preview_n') ?? $val('wave_size_n')),
                'expires_s'             => (int)   $val('offer_expires_sec'),
                'lead_time_min'         => (int)   $val('lead_time_min'),
                'use_google_for_eta'    => (bool)  $val('use_google_for_eta'),
                'allow_fare_bidding'    => (bool)  $val('allow_fare_bidding'),
                'auto_assign_if_single' => (bool)  $val('auto_assign_if_single'),
                'max_queue'             => (int)   $val('max_queue'),
                'queue_sla_minutes'     => (int)   $val('queue_sla_minutes'),
                'central_priority'      => (bool)  $val('central_priority'),
                'availability_min_ratio'=> $val('availability_min_ratio') !== null ? (float)$val('availability_min_ratio') : null,
                'extras'                => $val('extras'),
            ];
        });
    }
}
