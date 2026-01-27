<?php

namespace App\Http\Controllers\Partner;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Partner;
use App\Services\PartnerWalletService;
use App\Services\PartnerBillingUIService;
use App\Services\PartnerPrepaidBillingService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PartnerDashboardController extends BasePartnerController
{
    public function index(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $partner = Partner::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($partnerId)
            ->firstOrFail();

        // Flota
        $vehiclesTotal = (int) DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->count();

        $vehiclesActive = (int) DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('active', 1)
            ->count();

        $vehiclesPendingVerify = (int) DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->whereIn('active', [0,1])
            ->where('verification_status', '<>', 'verified')
            ->count();

        // Conductores
        $driversTotal = (int) DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->count();

        $driversEnabled = (int) DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('active', 1)
            ->count();

        $driversPendingVerify = (int) DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('verification_status', '<>', 'verified')
            ->count();

        $openAssignments = (int) DB::table('driver_vehicle_assignments as a')
            ->join('drivers as d', function($j) use ($tenantId, $partnerId){
                $j->on('d.id','=','a.driver_id')
                  ->where('d.tenant_id','=',$tenantId)
                  ->where('d.partner_id','=',$partnerId);
            })
            ->where('a.tenant_id', $tenantId)
            ->whereNull('a.end_at')
            ->count();

        // Wallet + UI state
        $wallet  = PartnerWalletService::ensureWallet($tenantId, $partnerId);
        $balance = (float)($wallet->balance ?? 0);

        $ui = PartnerBillingUIService::uiState(
            tenantId: $tenantId,
            partnerId: $partnerId,
            balance: $balance
        );

        /** @var PartnerPrepaidBillingService $billing */
        $billing  = app(PartnerPrepaidBillingService::class);
        $forecast = $billing->forecastPartner($tenantId, $partnerId);

        // Actividad reciente
        $recentTopups = DB::table('partner_topups')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentMovements = DB::table('partner_wallet_movements')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->limit(8)
            ->get();


        // =========================
        // Actividad diaria (últimos 14 días) por partner (flota)
        // =========================
        $days = 14;
        $to   = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $tenantId  = $tenantId;   // ya lo tienes
        $partnerId = $partnerId;

        $vehicleIdsSub = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id');

        $daily = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->whereIn('r.vehicle_id', $vehicleIdsSub)
            ->whereIn('r.status', ['finished','canceled'])
            ->whereRaw("COALESCE(r.finished_at, r.canceled_at) >= ? AND COALESCE(r.finished_at, r.canceled_at) <= ?", [
                $from->toDateString().' 00:00:00',
                $to->toDateString().' 23:59:59',
            ])
            ->selectRaw("DATE(COALESCE(r.finished_at, r.canceled_at)) as dia")
            ->selectRaw("SUM(r.status='finished') as finished")
            ->selectRaw("SUM(r.status='canceled') as canceled")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy(fn($x) => (string)$x->dia);

        $labels = [];
        $finished = [];
        $canceled = [];

        foreach (CarbonPeriod::create($from, $to) as $d) {
            $key = $d->toDateString();
            $row = $daily->get($key);

            $labels[]   = $d->translatedFormat('d M');
            $finished[] = (int)($row->finished ?? 0);
            $canceled[] = (int)($row->canceled ?? 0);
        }

        $activityChart = [
            'labels'   => $labels,
            'finished' => $finished,
            'canceled' => $canceled,
        ];


        return view('partner.dashboard', [
            'partner' => $partner,
            'stats' => [
                'vehicles_total' => $vehiclesTotal,
                'vehicles_active' => $vehiclesActive,
                'vehicles_pending_verify' => $vehiclesPendingVerify,

                'drivers_total' => $driversTotal,
                'drivers_enabled' => $driversEnabled,
                'drivers_pending_verify' => $driversPendingVerify,

                'open_assignments' => $openAssignments,

                'wallet_balance' => $balance,
                'wallet_currency' => $wallet->currency ?? 'MXN',
            ],
            'ui' => $ui,
            'activityChart' => $activityChart,

            'forecast' => $forecast,
            'recentTopups' => $recentTopups,
            'recentMovements' => $recentMovements,
        ]);
    }
}
