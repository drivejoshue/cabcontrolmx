<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Billing\BillingGate;
use Closure;
use Illuminate\Http\Request;

class TenantBillingOkApi
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenantId = (int)($user->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json([
                'ok' => false,
                'error' => 'tenant_missing',
                'message' => 'Usuario sin tenant asignado'
            ], 403);
        }

        $tenant = Tenant::with('billingProfile')->find($tenantId);
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'error' => 'tenant_not_found',
                'message' => 'Tenant no encontrado'
            ], 403);
        }

        $gate = new BillingGate();
        [$allowed, $code, $message] = $gate->decisionForTenant($tenant);

        if (!$allowed) {
            return response()->json([
                'ok' => false,
                'error' => 'tenant_billing_blocked',
                'reason' => $code,
                'message' => $message ?: 'Central suspendida por facturación'
            ], 403);
        }

        return $next($request);
    }
}
