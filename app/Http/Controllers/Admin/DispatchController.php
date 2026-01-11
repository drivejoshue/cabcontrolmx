<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Billing\BillingGate;

class DispatchController extends Controller
{
    public function index(BillingGate $gate)
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id, 403, 'Usuario sin tenant');

        $tenant = Tenant::with('billingProfile')->find($user->tenant_id);
        abort_unless($tenant, 404, 'Tenant no encontrado');

        // ✅ BLOQUEO server-side (antes de renderizar dispatch)
        [$allowed, $code, $message] = $gate->decisionForTenant($tenant);

        if (!$allowed) {
            // No cargues dispatch.js, no cargues mapa, no cargues nada de eso.
            return response()
                ->view('admin.dispatch_blocked', [
                    'tenant' => $tenant,
                    'code' => $code,
                    'message' => $message,
                ], 403);
        }

        // Normal
        $ccTenant = [
            'id'   => (int)$tenant->id,
            'name' => (string)$tenant->name,
            'map'  => [
                'lat'       => $tenant->latitud !== null ? (float)$tenant->latitud : null,
                'lng'       => $tenant->longitud !== null ? (float)$tenant->longitud : null,
                'zoom'      => $tenant->map_zoom ? (int)$tenant->map_zoom : 14,
                'radius_km' => $tenant->coverage_radius_km ? (float)$tenant->coverage_radius_km : 8,
            ],
            'map_icons' => [
                'origin' => '/images/origen.png',
                'dest'   => '/images/destino.png',
                'stand'  => '/images/marker-parqueo5.png',
                'stop'   => '/images/stopride.png',
            ],
        ];

        return view('admin.dispatch', compact('tenant', 'ccTenant'));
    }
}
