<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PartnerPrepaidBillingService;

class PartnerBillingGate
{
    public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if (!$user) return $next($request);

    $tenantId = (int)($user->tenant_id ?? 0);

    // partner.ctx idealmente coloca partner_id en attributes/session
    $partnerId = (int)($request->attributes->get('partner_id') ?? 0);
    if ($partnerId <= 0) $partnerId = (int)($user->default_partner_id ?? 0);

    if ($tenantId <= 0 || $partnerId <= 0) return $next($request);

    /** @var \App\Services\PartnerPrepaidBillingService $svc */
    $svc = app(\App\Services\PartnerPrepaidBillingService::class);

    // 1) Intentar liquidar cargos pendientes (si hay saldo) antes de evaluar gate
    try {
        // params: tenantId, partnerId, now=null, limit=31 (o el que uses)
        $svc->settleOutstandingChargesForPartner($tenantId, $partnerId, null, 31);
    } catch (\Throwable $e) {
        // No bloquees navegación por un error contable puntual
        // logger()->warning("settleOutstandingChargesForPartner failed: ".$e->getMessage(), [
        //     'tenant_id' => $tenantId,
        //     'partner_id' => $partnerId,
        // ]);
    }

    // 2) Calcular gate real ya con backlog liquidado si era posible
    $gate = $svc->partnerGateState($tenantId, $partnerId);

    // Guardar en request para banner persistente (ok/grace/blocked)
    $request->attributes->set('partner_billing_gate', $gate);

    // Si no está bloqueado, sigue normal
    if (($gate['state'] ?? 'ok') !== 'blocked') {
        return $next($request);
    }

    $routeName = (string) optional($request->route())->getName();

    if ($this->allowedWhenBlocked($routeName)) {
        return $next($request);
    }

    if ($request->expectsJson() || str_starts_with($request->path(), 'partner/api')) {
        return response()->json([
            'ok'   => false,
            'code' => 'partner_blocked',
            'msg'  => 'Partner bloqueado por adeudo. Solo se permite reportar recarga para desbloquear.',
            'gate' => $gate,
        ], 402);
    }

    return redirect()
        ->route('partner.topups.create')
        ->with('error', 'Partner bloqueado por adeudo. Reporta tu recarga para desbloquear.');
}

    private function allowedWhenBlocked(string $routeName): bool
    {
        if ($routeName === '') return false;

        // Dashboard puede ser permitido si lo conviertes en "Bloqueado"
        if ($routeName === 'partner.dashboard') return true;

        // Switch partner activo
        if ($routeName === 'partner.switch') return true;

        // Wallet + Topups (reportar recarga)
        if (str_starts_with($routeName, 'partner.wallet.')) return true;
        if (str_starts_with($routeName, 'partner.topups.')) return true;

        // Soporte / inbox opcionalmente
        if (str_starts_with($routeName, 'partner.support.')) return true;
        if (str_starts_with($routeName, 'partner.inbox.')) return true;

        return false;
    }
}
