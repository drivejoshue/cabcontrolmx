<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\PartnerTopup;
use App\Models\PartnerWallet;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Partner $partner */
        $partner = $request->attributes->get('partner');
        if (!$partner) abort(500, 'Partner context missing');

        $tenantId  = (int)$partner->tenant_id;
        $partnerId = (int)$partner->id;

        // Métricas SOLO de control operativo del partner (vehículos, drivers, wallet/topups)
        $vehiclesTotal  = Vehicle::where('tenant_id', $tenantId)->where('partner_id', $partnerId)->count();
        $vehiclesActive = Vehicle::where('tenant_id', $tenantId)->where('partner_id', $partnerId)->where('active', 1)->count();

        $driversTotal = Driver::where('tenant_id', $tenantId)->where('partner_id', $partnerId)->count();
        $driversActive = Driver::where('tenant_id', $tenantId)->where('partner_id', $partnerId)
            ->whereIn('status', ['idle','busy','on_ride']) // ajusta a tus estados reales
            ->count();

        $wallet = PartnerWallet::firstOrCreate(
            ['tenant_id' => $tenantId, 'partner_id' => $partnerId],
            ['balance' => 0, 'currency' => 'MXN']
        );
        $balance = $wallet->balance;
        $recentTopups = PartnerTopup::where('tenant_id', $tenantId)->where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('partner.dashboard', [
            'partner' => $partner,
            'stats' => [
                'vehicles_total' => $vehiclesTotal,
                'vehicles_active' => $vehiclesActive,
                'drivers_total' => $driversTotal,
                'drivers_active' => $driversActive,
                'wallet_balance' => $balance,
                'wallet_currency' => $wallet?->currency ?? 'MXN',
            ],
            'recentTopups' => $recentTopups,
        ]);
    }
}
