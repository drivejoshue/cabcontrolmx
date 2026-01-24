<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\DispatchSetting;

class DispatchSettingsController extends Controller
{
    private const CORE_TENANT_ID = 100;

    private function requestedTenantIdFrom(Request $request): int
    {
        $tid = $request->header('X-Tenant-ID')
            ?? $request->query('tenant_id')
            ?? optional(Auth::user())->tenant_id;

        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int) $tid;
    }

    private function effectiveTenantId(Request $request): int
    {
        return self::CORE_TENANT_ID;
    }

    /**
     * Permitir edición a cualquier usuario autenticado dentro del panel admin.
     * (El middleware del grupo /admin ya filtra roles.)
     */
    private function authorizeEdit(): void
    {
        abort_if(!Auth::check(), 403, 'No autorizado');
    }

    public function show(Request $request): JsonResponse
    {
        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        $s = \App\Services\DispatchSettingsService::forTenant($tenantId);

        Log::info('DispatchSettingsController - Settings unificados (core)', [
            'requestedTenantId' => $requestedTenantId,
            'effectiveTenantId' => $tenantId,
        ]);

        return response()->json([
            // Auto dispatch
            'auto_dispatch_enabled'           => (bool)  $s->enabled,
            'auto_dispatch_delay_s'           => (int)   $s->delay_s,
            'auto_dispatch_preview_radius_km' => (float) $s->radius_km,
            'auto_dispatch_preview_n'         => (int)   $s->limit_n,
            'offer_expires_sec'               => (int)   $s->expires_s,
            'auto_assign_if_single'           => (bool)  $s->auto_assign_if_single,

            // Fare bidding (ya lo estabas usando)
            'allow_fare_bidding'              => (bool)  $s->allow_fare_bidding,
        ]);
    }

    public function edit(Request $request)
    {
        $this->authorizeEdit();

        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        $row = DispatchSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                // Defaults 100% compatibles con tu tabla
                'auto_dispatch_radius_km'     => 5.00,
                'nearby_search_radius_km'     => 5.00,

                'stand_radius_km'             => 3.00,
                'stand_step_sec'              => 30,
                'stand_on_timeout'            => 'saltado',
                'stand_allow_onride'          => 0,
                'stand_onride_eta_bonus_sec'  => 300,

                'offer_expires_sec'           => 180,
                'offer_global_expires_sec'    => 180,
                'wave_size_n'                 => 8,
                'taxi_stands_enabled'         => 1,

                'driver_bid_step_bps'         => 800,
                'driver_bid_step_min_amount'  => 5,
                'driver_bid_step_max_amount'  => 25,
                'driver_bid_tiers'            => 3,
                'driver_bid_round_to'         => 5,

                'client_config_version'       => 1,
                'lead_time_min'               => 15,
                'use_google_for_eta'          => 1,
                'allow_fare_bidding'          => 0,

                'auto_enabled'                => 1,
                'auto_delay_sec'              => 5,
                'auto_assign_if_single'       => 0,
                'auto_dispatch_delay_s'       => 5,
                'auto_dispatch_preview_n'     => 8,

                'max_queue'                   => 2,
                'queue_sla_minutes'           => 20,
                'central_priority'            => 1,
                'availability_min_ratio'      => 3.00,
            ]
        );

        return view('admin.dispatch_settings.edit', [
            'row'               => $row,
            'tenantId'          => $tenantId,
            'requestedTenantId' => $requestedTenantId,
            'isCore'            => true,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeEdit();

        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        $data = $request->validate([
            // Auto
            'auto_enabled'              => 'required|in:0,1',
            'auto_delay_sec'            => 'required|integer|min:0|max:180',
            'auto_dispatch_delay_s'     => 'nullable|integer|min:1|max:180',
            'auto_dispatch_preview_n'   => 'required|integer|min:1|max:50',
            'auto_assign_if_single'     => 'required|in:0,1',

            // Radios
            'auto_dispatch_radius_km'   => 'required|numeric|min:0.5|max:60',
            'nearby_search_radius_km'   => 'required|numeric|min:0.5|max:200',

            // Taxi stands
            'taxi_stands_enabled'       => 'required|in:0,1',
            'stand_radius_km'           => 'required|numeric|min:0.2|max:200',
            'stand_step_sec'            => 'required|integer|min:10|max:120',
            'stand_on_timeout'          => 'required|in:saltado,salio',
            'stand_allow_onride'        => 'required|in:0,1',
            'stand_onride_eta_bonus_sec'=> 'required|integer|min:0|max:1800',

            // Ola / expiración
            'wave_size_n'               => 'required|integer|min:1|max:50',
            'offer_expires_sec'         => 'required|integer|min:20|max:900',
            'offer_global_expires_sec'  => 'nullable|integer|min:20|max:900',

            // Programados / ETA
            'lead_time_min'             => 'required|integer|min:0|max:240',
            'use_google_for_eta'        => 'required|in:0,1',

            // Bidding
            'allow_fare_bidding'        => 'required|in:0,1',
            'driver_bid_step_bps'       => 'required|integer|min:50|max:3000', // 0.5% .. 30%
            'driver_bid_step_min_amount'=> 'required|integer|min:0|max:9999',
            'driver_bid_step_max_amount'=> 'required|integer|min:0|max:9999',
            'driver_bid_tiers'          => 'required|integer|min:1|max:10',
            'driver_bid_round_to'       => 'required|integer|min:1|max:50',

            // Queue / SLA / prioridad
            'max_queue'                 => 'required|integer|min:0|max:20',
            'queue_sla_minutes'         => 'required|integer|min:1|max:180',
            'central_priority'          => 'required|in:0,1',
            'availability_min_ratio'    => 'nullable|numeric|min:0|max:10',

            // Client config
            'client_config_version'     => 'required|integer|min:1|max:1000000',
        ]);

        $row = DispatchSetting::firstOrCreate(['tenant_id' => $tenantId]);

        // ----- Normalización/clamps consistentes -----
        $offerExpires = max(20, min(900, (int)$data['offer_expires_sec']));
        $globalExpires = $data['offer_global_expires_sec'] !== null
            ? max(20, min(900, (int)$data['offer_global_expires_sec']))
            : null;

        // Si global viene vacío, usa offer_expires
        if ($globalExpires === null) $globalExpires = $offerExpires;

        $bidMin = max(0, (int)$data['driver_bid_step_min_amount']);
        $bidMax = max(0, (int)$data['driver_bid_step_max_amount']);
        if ($bidMax < $bidMin) $bidMax = $bidMin; // coherencia

        $delay = (int) $data['auto_delay_sec'];
        $uiDelay = $data['auto_dispatch_delay_s'] !== null ? (int)$data['auto_dispatch_delay_s'] : null;
        if ($uiDelay === null || $uiDelay <= 0) $uiDelay = max(1, $delay);

        // ----- Persistencia -----
        $row->auto_enabled            = (int)$data['auto_enabled'];
        $row->auto_delay_sec          = $delay;
        $row->auto_dispatch_delay_s   = $uiDelay;
        $row->auto_dispatch_preview_n = (int)$data['auto_dispatch_preview_n'];
        $row->auto_assign_if_single   = (int)$data['auto_assign_if_single'];

        $row->auto_dispatch_radius_km = (float)$data['auto_dispatch_radius_km'];
        $row->nearby_search_radius_km = (float)$data['nearby_search_radius_km'];

        $row->taxi_stands_enabled        = (int)$data['taxi_stands_enabled'];
        $row->stand_radius_km            = (float)$data['stand_radius_km'];
        $row->stand_step_sec             = (int)$data['stand_step_sec'];
        $row->stand_on_timeout           = (string)$data['stand_on_timeout'];
        $row->stand_allow_onride         = (int)$data['stand_allow_onride'];
        $row->stand_onride_eta_bonus_sec = (int)$data['stand_onride_eta_bonus_sec'];

        $row->wave_size_n              = (int)$data['wave_size_n'];
        $row->offer_expires_sec        = $offerExpires;
        $row->offer_global_expires_sec = $globalExpires;

        $row->lead_time_min            = (int)$data['lead_time_min'];
        $row->use_google_for_eta       = (int)$data['use_google_for_eta'];

        $row->allow_fare_bidding       = (int)$data['allow_fare_bidding'];
        $row->driver_bid_step_bps      = (int)$data['driver_bid_step_bps'];
        $row->driver_bid_step_min_amount = $bidMin;
        $row->driver_bid_step_max_amount = $bidMax;
        $row->driver_bid_tiers         = (int)$data['driver_bid_tiers'];
        $row->driver_bid_round_to      = (int)$data['driver_bid_round_to'];

        $row->max_queue                = (int)$data['max_queue'];
        $row->queue_sla_minutes        = (int)$data['queue_sla_minutes'];
        $row->central_priority         = (int)$data['central_priority'];
        $row->availability_min_ratio   = $data['availability_min_ratio'] !== null ? (float)$data['availability_min_ratio'] : null;

        $row->client_config_version    = (int)$data['client_config_version'];

        $row->save();

        if (method_exists(\App\Services\DispatchSettingsService::class, 'forgetTenant')) {
            \App\Services\DispatchSettingsService::forgetTenant($tenantId);
        }

        Log::info('DispatchSettingsController - Update (core)', [
            'requestedTenantId' => $requestedTenantId,
            'effectiveTenantId' => $tenantId,
            'by_user_id'        => (int) optional(Auth::user())->id,
        ]);

        return redirect()
            ->route('admin.dispatch_settings.edit')
            ->with('ok', 'Ajustes de despacho guardados (Orbana Core).');
    }
}
