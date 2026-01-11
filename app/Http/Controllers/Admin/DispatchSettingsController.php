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
    private function tenantIdFrom(Request $request): int
    {
        $tid = $request->header('X-Tenant-ID')
            ?? $request->query('tenant_id')
            ?? optional(Auth::user())->tenant_id;

        if (!$tid) abort(403, 'Usuario sin tenant asignado');

        return (int)$tid;
    }

    /**
     * JSON settings unificados (para JS / dispatch).
     * Mantiene el contrato que ya estás usando en el front:
     * - auto_dispatch_enabled
     * - auto_dispatch_delay_s
     * - auto_dispatch_preview_radius_km
     * - auto_dispatch_preview_n
     * - offer_expires_sec
     * - auto_assign_if_single
     * - allow_fare_bidding
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->tenantIdFrom($request);

        $s = \App\Services\DispatchSettingsService::forTenant($tenantId);

        Log::info('DispatchSettingsController - Settings unificados', [
            'tenantId'   => $tenantId,
            'enabled'    => (bool)$s->enabled,
            'delay_s'    => (int)$s->delay_s,
            'radius_km'  => (float)$s->radius_km,
            'limit_n'    => (int)$s->limit_n,
            'expires_s'  => (int)$s->expires_s,
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
        $tenantId = $this->tenantIdFrom($request);

        $row = DispatchSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                // defaults alineados a TU tabla real
                'auto_enabled'            => 1,
                'auto_delay_sec'          => 0,
                'auto_dispatch_radius_km' => 8,
                'wave_size_n'             => 8,
                'offer_expires_sec'       => 300,
                'auto_assign_if_single'   => 0,
                'allow_fare_bidding'      => 0,
                // “preview” opcionales si tu UI los usa
                'auto_dispatch_delay_s'   => 0,
                'auto_dispatch_preview_n' => 8,
            ]
        );

        return view('admin.dispatch_settings.edit', [
            'row'      => $row,
            'tenantId' => $tenantId,
        ]);
    }

    // UI PUT: /admin/dispatch-settings
public function update(Request $request)
{
    $tenantId = $this->tenantIdFrom($request);

    $data = $request->validate([
        // Auto dispatch
        'auto_enabled'            => 'required|in:0,1',

        // NUNCA 0: delay mínimo seguro
        'auto_dispatch_delay_s'   => 'required|integer|min:1|max:180',

        'auto_dispatch_preview_n' => 'required|integer|min:1|max:50',

        // Radios mínimos seguros
        'auto_dispatch_radius_km' => 'required|numeric|min:0.5|max:60',

        // Olas & expiración
        'wave_size_n'             => 'required|integer|min:1|max:50',

        // NUNCA menor a 20 por red y reacción humana
        'offer_expires_sec'       => 'required|integer|min:20|max:900',

        'lead_time_min'           => 'required|integer|min:0|max:240',
        'auto_assign_if_single'   => 'required|in:0,1',

        // Búsqueda & bases (mínimos seguros)
        'nearby_search_radius_km' => 'required|numeric|min:0.5|max:200',
        'stand_radius_km'         => 'required|numeric|min:0.2|max:200',

        'use_google_for_eta'      => 'required|in:0,1',
    ]);

    $row = DispatchSetting::firstOrCreate(['tenant_id' => $tenantId]);

    // Flags
    $row->auto_enabled          = (int)$data['auto_enabled'];
    $row->auto_assign_if_single = (int)$data['auto_assign_if_single'];
    $row->use_google_for_eta    = (int)$data['use_google_for_eta'];

    // Clamp extra (por si algo raro pasa)
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

    if (method_exists(\App\Services\DispatchSettingsService::class, 'forgetTenant')) {
        \App\Services\DispatchSettingsService::forgetTenant($tenantId);
    }

    return redirect()
        ->route('admin.dispatch_settings.edit')
        ->with('ok', 'Ajustes de despacho guardados.');
}


}
