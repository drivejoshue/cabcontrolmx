<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantWalletTopupController extends Controller
{
    public function create()
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        if ($tenantId <= 0) abort(403);

        return view('admin.wallet.topup_create');
    }

    public function store(Request $r, TenantWalletService $wallet)
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        if ($tenantId <= 0) abort(403);

        $data = $r->validate([
            'amount' => ['required','numeric','min:1'],
            'notes'  => ['nullable','string','max:255'],
        ]);

        // SIMULACIÓN (manual). Luego esto se reemplaza por “pago aprobado” de pasarela.
        $wallet->creditTopup(
            $tenantId,
            (float)$data['amount'],
            'MANUAL-'.now()->format('YmdHis'),
            $data['notes'] ?? 'Recarga manual (simulación)'
        );

        return redirect()->route('admin.billing.plan')
            ->with('success', 'Recarga aplicada al wallet.');
    }
}
