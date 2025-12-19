<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantBillingService;
use App\Services\TenantWalletService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTenantSufficientBalance
{
    public function __construct(
        private TenantBillingService $billing,
        private TenantWalletService $wallet
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $u = Auth::user();
        $tenantId = (int)($u->tenant_id ?? 0);
        if ($tenantId <= 0) abort(403, 'Usuario sin tenant');

        /** @var Tenant $tenant */
        $tenant = $u->tenant;
        if (!$tenant) abort(403, 'Tenant no encontrado');

        // Solo aplica a per_vehicle (en comisión NO bloqueamos por wallet)
        $profile = $tenant->billingProfile;
        if (!$profile || ($profile->billing_model ?? null) !== 'per_vehicle') {
            return $next($request);
        }

        // Si está cancelado => bloquea (pero esto normalmente ya lo manejas con billing_state)
        $st = strtolower((string)($profile->status ?? ''));
        if (in_array($st, ['canceled'], true)) {
            return $this->deny($request, 403, 'billing_canceled', 'Tu servicio está cancelado.');
        }

        // Si está suspendido/paused => NO dejamos entrar a nada operativo
        // (pero permitir Billing/Wallet se hace excluyendo rutas del middleware; ver sección rutas)
        if (!empty($profile->suspended_at) || $st === 'paused') {
            return $this->deny($request, 402, 'billing_suspended', 'Servicio suspendido. Recarga para reactivar.');
        }

        // Regla: mínimo requerido para terminar el mes desde "hoy"
        $calc = $this->billing->requiredBalanceToFinishMonth($tenant, now());
        $missing = (float)($calc['missing'] ?? 0);

        if ($missing > 0) {
            // Si es request web normal => redirige a wallet
            if (!$request->expectsJson()) {
                return redirect()
                    ->route('admin.wallet.index')
                    ->with('warning', "Saldo insuficiente. Faltan $".number_format($missing, 2)." MXN para continuar. Recarga el wallet.");
            }

            // Si es JSON (por si luego lo usas en endpoints)
            return response()->json([
                'ok' => false,
                'code' => 'insufficient_balance',
                'message' => 'Saldo insuficiente para continuar.',
                'required' => $calc,
            ], 402);
        }

        return $next($request);
    }

    private function deny(Request $request, int $http, string $code, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'code' => $code, 'message' => $message], $http);
        }

        return redirect()
            ->route('admin.billing.plan')
            ->with('warning', $message);
    }
}
