<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantWalletService;
use App\Services\TenantBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantWalletController extends Controller
{
    /**
     * Pantalla principal del wallet (saldo + movimientos + sugerencia).
     */
    public function index(TenantWalletService $wallet, TenantBillingService $billing)
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);

        // 1) Wallet (ensure)
        $w = $wallet->ensureWallet($tenantId);

        // 2) Movimientos
        $movs = DB::table('tenant_wallet_movements')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // 3) Estimación del siguiente cargo (si ya existe el método)
        $tenant = Auth::user()->tenant;

        $next = null;
        if (method_exists($billing, 'estimatedNextCharge')) {
            $next = $billing->estimatedNextCharge($tenant, now());
        }

        return view('admin.wallet.index', [
            'wallet'     => $w,
            'movements'  => $movs,
            'nextCharge' => $next, // <- IMPORTANTE
        ]);
    }

    /**
     * Alias compatible: /admin/wallet/movements
     * (misma vista y misma data para evitar desfaces)
     */
    public function movements(TenantWalletService $wallet, TenantBillingService $billing)
    {
        return $this->index($wallet, $billing);
    }

    /**
     * Recarga manual (simulación). Temporal para pruebas.
     */
    public function topupManual(Request $r, TenantWalletService $wallet)
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);

        $data = $r->validate([
            'amount' => 'required|numeric|min:10|max:200000',
            'notes'  => 'nullable|string|max:255',
        ]);

        // Usa el método que ya dejaste funcionando (ajústalo al real):
        // topupConfirmed() o creditTopup() según tu implementación final.
        if (method_exists($wallet, 'topupConfirmed')) {
            $wallet->topupConfirmed(
                $tenantId,
                (float)$data['amount'],
                'manual:'.now()->format('YmdHis'),
                $data['notes'] ?? 'Recarga manual (simulación)'
            );
        } else {
            // fallback si tu service usa creditTopup()
            $wallet->creditTopup(
                $tenantId,
                (float)$data['amount'],
                'MANUAL-'.now()->format('YmdHis'),
                $data['notes'] ?? 'Recarga manual (simulación)'
            );
        }

        return back()->with('ok', 'Recarga aplicada.');
    }
}
