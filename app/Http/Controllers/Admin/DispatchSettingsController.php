<?php
// app/Http/Controllers/Admin/DispatchSettingsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\DispatchSetting;

class DispatchSettingsController extends Controller
{
    // “Cerebro” global
    private const CORE_TENANT_ID = 100;

    /**
     * Tenant "solicitado" (header/query/user). Sirve para logs, auditoría
     * y para un futuro regresar a configuración por tenant sin romper contrato.
     */
    private function requestedTenantIdFrom(Request $request): int
    {
        $tid = $request->header('X-Tenant-ID')
            ?? $request->query('tenant_id')
            ?? optional(Auth::user())->tenant_id;

        if (!$tid) abort(403, 'Usuario sin tenant asignado');

        return (int)$tid;
    }

    /**
     * Tenant efectivo (hoy SIEMPRE = tenant 100).
     * Deja este método para revertir el comportamiento en el futuro.
     */
    private function effectiveTenantId(Request $request): int
    {
        // Hoy el autodespacho NO es configurable por tenants.
        return self::CORE_TENANT_ID;
    }

    /**
     * Gate mínimo para la UI de edición (solo SysAdmin o estar en tenant 100).
     * show() lo dejamos abierto porque el Dispatch/JS puede necesitar leer settings
     * (pero devolverá los del tenant 100).
     */
    private function authorizeCoreEdit(): void
    {
        $u = Auth::user();
        $isSysAdmin = (bool)($u?->is_sysadmin ?? false);
        $tenantId   = (int)($u?->tenant_id ?? 0);

        if (!$isSysAdmin && $tenantId !== self::CORE_TENANT_ID) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * JSON settings unificados (para JS / dispatch).
     * Mantiene el contrato que ya estás usando en el front.
     *
     * IMPORTANTE: devuelve SIEMPRE valores del tenant 100.
     */
    public function show(Request $request): JsonResponse
    {
        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        // Saca settings del "cerebro"
        $s = \App\Services\DispatchSettingsService::forTenant($tenantId);

        Log::info('DispatchSettingsController - Settings unificados (core fallback)', [
            'requestedTenantId' => $requestedTenantId,
            'effectiveTenantId' => $tenantId,
            'enabled'           => (bool)$s->enabled,
            'delay_s'           => (int)$s->delay_s,
            'radius_km'         => (float)$s->radius_km,
            'limit_n'           => (int)$s->limit_n,
            'expires_s'         => (int)$s->expires_s,
        ]);

        return response()->json([
            'auto_dispatch_enabled'           => (bool)  $s->enabled,
            'auto_dispatch_delay_s'           => (int)   $s->delay_s,
            'auto_dispatch_preview_radius_km' => (float) $s->radius_km,
            'auto_dispatch_preview_n'         => (int)   $s->limit_n,
            'offer_expires_sec'               => (int)   $s->expires_s,
            'auto_assign_if_single'           => (bool)  $s->auto_assign_if_single,
            'allow_fare_bidding'              => (bool)  $s->allow_fare_bidding,
        ]);
    }

    // UI GET: /admin/dispatch-settings
    public function edit(Request $request)
    {
        $this->authorizeCoreEdit();

        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        $row = DispatchSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                // defaults alineados a TU tabla real
                'auto_enabled'            => 1,
                'auto_delay_sec'          => 1,   // mínimo seguro
                'auto_dispatch_radius_km' => 8,
                'wave_size_n'             => 8,
                'offer_expires_sec'       => 300,
                'auto_assign_if_single'   => 0,
                'allow_fare_bidding'      => 0,

                // “preview” opcionales si tu UI los usa
                'auto_dispatch_delay_s'   => 5,
                'auto_dispatch_preview_n' => 8,
            ]
        );

        return view('admin.dispatch_settings.edit', [
            'row'               => $row,
            'tenantId'          => $tenantId,          // efectivo (100)
            'requestedTenantId' => $requestedTenantId, // informativo
            'isCore'            => true,
        ]);
    }

    // UI PUT: /admin/dispatch-settings
    public function update(Request $request)
    {
        $this->authorizeCoreEdit();

        $requestedTenantId = $this->requestedTenantIdFrom($request);
        $tenantId = $this->effectiveTenantId($request); // 100

        $data = $request->validate([
            'auto_enabled'            => 'required|in:0,1',

            // NUNCA 0
            'auto_dispatch_delay_s'   => 'required|integer|min:1|max:180',
            'auto_dispatch_preview_n' => 'required|integer|min:1|max:50',

            'auto_dispatch_radius_km' => 'required|numeric|min:0.5|max:60',
            'wave_size_n'             => 'required|integer|min:1|max:50',

            // NUNCA menor a 20
            'offer_expires_sec'       => 'required|integer|min:20|max:900',

            'lead_time_min'           => 'required|integer|min:0|max:240',
            'auto_assign_if_single'   => 'required|in:0,1',

            'nearby_search_radius_km' => 'required|numeric|min:0.5|max:200',
            'stand_radius_km'         => 'required|numeric|min:0.2|max:200',

            'use_google_for_eta'      => 'required|in:0,1',
        ]);

        $row = DispatchSetting::firstOrCreate(['tenant_id' => $tenantId]);

        // Flags
        $row->auto_enabled          = (int)$data['auto_enabled'];
        $row->auto_assign_if_single = (int)$data['auto_assign_if_single'];
        $row->use_google_for_eta    = (int)$data['use_google_for_eta'];

        // Clamp extra
        $delay   = max(1, min(180, (int)$data['auto_dispatch_delay_s']));
        $expires = max(20, min(900, (int)$data['offer_expires_sec']));

        $row->auto_dispatch_radius_km   = max(0.5, (float)$data['auto_dispatch_radius_km']);
        $row->auto_dispatch_preview_n   = max(1, (int)$data['auto_dispatch_preview_n']);

        $row->auto_delay_sec            = $delay;
        $row->auto_dispatch_delay_s     = $delay;

        $row->wave_size_n               = max(1, (int)$data['wave_size_n']);
        $row->offer_expires_sec         = $expires;
        $row->lead_time_min             = max(0, (int)$data['lead_time_min']);

        $row->nearby_search_radius_km   = max(0.5, (float)$data['nearby_search_radius_km']);
        $row->stand_radius_km           = max(0.2, (float)$data['stand_radius_km']);

        $row->save();

        // Limpia cache de settings del tenant efectivo (100)
        if (method_exists(\App\Services\DispatchSettingsService::class, 'forgetTenant')) {
            \App\Services\DispatchSettingsService::forgetTenant($tenantId);
        }

        Log::info('DispatchSettingsController - Update settings (core)', [
            'requestedTenantId' => $requestedTenantId,
            'effectiveTenantId' => $tenantId,
            'by_user_id'        => (int)optional(Auth::user())->id,
        ]);

        return redirect()
            ->route('admin.dispatch_settings.edit')
            ->with('ok', 'Ajustes de despacho guardados (Orbana Core).');
    }
}
