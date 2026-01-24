<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PartnerWalletService;
use App\Services\PartnerBillingUIService;
use App\Services\PartnerPrepaidBillingService;

class PartnerWalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = (int)($user->tenant_id ?? 0);

        // ✅ Fuente canónica: partner.ctx -> attributes; fallback session
        $partnerId = (int)($request->attributes->get('partner_id') ?? 0);
        if ($partnerId <= 0) $partnerId = (int) session('partner_id');

        if ($tenantId <= 0 || $partnerId <= 0) {
            abort(403, 'Falta contexto de partner.');
        }

        $partner = Partner::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($partnerId)
            ->firstOrFail();

        $wallet  = PartnerWalletService::ensureWallet($tenantId, $partnerId);
        $balance = (float)($wallet->balance ?? 0);

        // =========================
        // ✅ UI State (lo que ya usas hoy)
        // =========================
        $ui = PartnerBillingUIService::uiState(
            tenantId: $tenantId,
            partnerId: $partnerId,
            balance: $balance
        );

        $summary       = $ui['summary'] ?? [];
        $requiredToAdd = $ui['required_to_add_vehicle_today'] ?? [];

        // =========================
        // ✅ Forecast (fuente única para proyección / sugerencia)
        // =========================
        /** @var PartnerPrepaidBillingService $billing */
        $billing  = app(PartnerPrepaidBillingService::class);
        $forecast = $billing->forecastPartner($tenantId, $partnerId);

        // Por compatibilidad con tu blade actual, expón 3 variables "legacy"
        $projectedEnd   = (float)($forecast['end_month_balance_est'] ?? 0);
        $nextMonthCost  = (float)($forecast['next_month_cost_est'] ?? 0);
        $topupSuggested = (float)($forecast['recommended_topup_for_next_month'] ?? 0);

        // =========================
        // ✅ Movimientos enriquecidos
        // =========================
        $movements = DB::table('partner_wallet_movements as m')
            ->where('m.tenant_id', $tenantId)
            ->where('m.partner_id', $partnerId)
            ->leftJoin('partner_daily_charges as c', function ($j) {
                $j->on('m.ref_id', '=', 'c.id')
                  ->where('m.ref_type', '=', 'partner_daily_charges');
            })
            ->leftJoin('partner_topups as t', function ($j) {
                $j->on('m.ref_id', '=', 't.id')
                  ->where('m.ref_type', '=', 'partner_topups');
            })
            ->select([
                'm.id','m.tenant_id','m.partner_id','m.type','m.direction','m.amount','m.balance_after','m.currency',
                'm.ref_type','m.ref_id','m.external_ref','m.notes','m.meta','m.created_at',

                // charges
                'c.charge_date',
                'c.vehicles_count as charge_vehicles',
                'c.daily_rate as charge_rate',
                'c.amount as charge_amount',
                'c.settled_at',

                // topups
                't.status as topup_status',
                't.method as topup_method',
                't.provider as topup_provider',
                't.external_reference as topup_external_reference',
                't.bank_ref as topup_bank_ref',
                't.payer_ref as topup_payer_ref',
                't.deposited_at as topup_deposited_at',
                't.review_status as topup_review_status',
                't.reviewed_at as topup_reviewed_at',
                't.credited_at as topup_credited_at',
                't.mp_payment_id as topup_mp_payment_id',
            ])
            ->orderByDesc('m.id')
            ->paginate(50);

        return view('partner.wallet.index', compact(
            'partner',
            'wallet',
            'movements',
            'ui',
            'summary',
            'requiredToAdd',
            'forecast',
            'projectedEnd',
            'nextMonthCost',
            'topupSuggested'
        ));
    }
}
