<?php
namespace App\Services;

use App\Models\Tenant;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\PartnerWalletService;
use App\Services\TenantWalletService;

class PartnerPrepaidBillingService
{
    private int $GRACE_DAYS = 5;

    public function tenantTzNow(Tenant $tenant, ?Carbon $now = null): Carbon
    {
        $now = $now ?: Carbon::now();
        return $tenant->timezone ? $now->copy()->setTimezone($tenant->timezone) : $now->copy();
    }

    public function daysInMonth(Carbon $date): int
    {
        return (int)$date->daysInMonth;
    }

    public function dailyRateForTenant(Tenant $tenant, Carbon $date): float
    {
        $p = $tenant->billingProfile;
        $ppv = (float)($p->price_per_vehicle ?? 0);

        $days = max(1, $this->daysInMonth($date));
        return round($ppv / $days, 4); // 4 decimales para estabilidad
    }

    public function activePartnerVehiclesOnDate(int $tenantId, int $partnerId, Carbon $dateLocal): int
    {
        $d = $dateLocal->toDateString();

        return (int) DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('active', 1)
            ->whereDate('partner_assigned_at', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('partner_left_at')
                  ->orWhereDate('partner_left_at', '>', $d);
            })
            ->count();
    }

    /**
     * Verifica si el tenant está en modo partner
     */
    private function isPartnerMode(Tenant $tenant): bool
    {
        return $tenant->partner_billing_wallet === 'partner_wallet';
    }

    /**
     * Requerido para “activar 1 vehículo hoy”:
     * días_restantes_inclusive * daily_rate
     */
    public function requiredToAddVehicleToday(Tenant $tenant, int $partnerId, ?Carbon $now = null, int $adding = 1): array
    {
        $tzNow = $this->tenantTzNow($tenant, $now);
        $start = $tzNow->copy()->startOfDay();
        $end   = $tzNow->copy()->endOfMonth()->startOfDay();

        $daysRemaining = $start->diffInDays($end) + 1; // inclusive
        $rate = $this->dailyRateForTenant($tenant, $tzNow);

        $requiredTotal = round($daysRemaining * $rate * max(1, $adding), 2);

        $wallet = PartnerWalletService::ensureWallet((int)$tenant->id, (int)$partnerId);
        $bal = (float)($wallet->balance ?? 0);

        return [
            'required_total' => $requiredTotal,
            'topup_needed' => max(0, round($requiredTotal - $bal, 2)),
            'currency' => $wallet->currency ?? 'MXN',
            'period_start' => $start->toDateString(),
            'period_end' => $tzNow->copy()->endOfMonth()->toDateString(),
            'daily_rate' => round($rate, 4),
        ];
    }

    /**
     * Cobro diario idempotente: (tenant_id, partner_id, charge_date)
     * - Debita PartnerWallet (reserva)
     * - Debita TenantWallet (settlement / salida de escrow)
     */
    public function chargeOneDayForTenant(int $tenantId, ?Carbon $now = null): array
    {
        $tenant = Tenant::with('billingProfile')->findOrFail($tenantId);
        if (!$this->isPartnerMode($tenant)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'not_partner_mode'];
        }

        $localNow = $this->tenantTzNow($tenant, $now);
        $chargeDate = $localNow->toDateString();
        $rate = $this->dailyRateForTenant($tenant, $localNow);
        $currency = 'MXN';

        // Partners “en juego”: unión de partner_wallets y vehicles.partner_id
        $idsA = DB::table('partner_wallets')->where('tenant_id', $tenantId)->pluck('partner_id');
        $idsB = DB::table('vehicles')->where('tenant_id', $tenantId)->whereNotNull('partner_id')->distinct()->pluck('partner_id');
        $partnerIds = $idsA->merge($idsB)->unique()->values();

        /** @var TenantWalletService $tw */
        $tw = app(TenantWalletService::class);
        $tw->ensureWallet($tenantId);

        $totals = ['partners' => 0, 'amount' => 0.0];

        foreach ($partnerIds as $pid) {
            $partnerId = (int)$pid;

            DB::transaction(function () use ($tenantId, $partnerId, $chargeDate, $rate, $currency, $tw, &$totals) {

                // Idempotencia
                $exists = DB::table('partner_daily_charges')
                    ->where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->where('charge_date', $chargeDate)
                    ->lockForUpdate()
                    ->first();

                if ($exists) return;

                // Vehículos activos del partner "en esa fecha"
                $vehiclesCount = (int) DB::table('vehicles')
                    ->where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->where('active', 1)
                    ->whereRaw("DATE(COALESCE(partner_assigned_at, created_at)) <= ?", [$chargeDate])
                    ->where(function ($q) use ($chargeDate) {
                        $q->whereNull('partner_left_at')
                          ->orWhereDate('partner_left_at', '>', $chargeDate);
                    })
                    ->count();

                $amount = round($vehiclesCount * $rate, 2);

                $chargeId = DB::table('partner_daily_charges')->insertGetId([
                  'tenant_id'      => $tenantId,
                  'partner_id'     => $partnerId,
                  'charge_date'    => $chargeDate,
                  'vehicles_count' => $vehiclesCount,
                  'daily_rate'     => $rate,
                  'amount'         => $amount,
                  'paid_amount'    => 0,
                  'unpaid_amount'  => $amount,   // << clave
                  'currency'       => $currency,
                  'created_at'     => now(),
                  'updated_at'     => now(),
                ]);


                if ($amount <= 0) {
                    $totals['partners']++;
                    return;
                }

                // 1) Debitar partner (si no alcanza, NO cobramos y queda para gracia/bloqueo)
                $pw = \App\Models\PartnerWallet::where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->lockForUpdate()
                    ->first();

                if (!$pw) {
                    $pw = PartnerWalletService::ensureWallet($tenantId, $partnerId);
                    $pw = \App\Models\PartnerWallet::where('id', $pw->id)->lockForUpdate()->first();
                }

                $bal = (float)($pw->balance ?? 0);
                if ($bal + 1e-9 < $amount) {
                    // saldo insuficiente -> queda sin settlement, tu UI lo marca como "grace"
                    $totals['partners']++;
                    return;
                }

                $extPartner = 'partner_daily_charge:' . (int)$chargeId;
                PartnerWalletService::debit(
                    tenantId: $tenantId,
                    partnerId: $partnerId,
                    amount: (string)number_format($amount, 2, '.', ''),
                    currency: $currency,
                    type: 'billing',
                    refType: 'partner_daily_charges',
                    refId: (int)$chargeId,
                    externalRef: $extPartner,
                    notes: 'Cargo diario por vehículos (partner-mode)',
                    meta: ['charge_date' => $chargeDate]
                );

                // 2) Settlement: debitar tenant escrow (idempotente)
                $extTenant = 'settlement_partner_daily_charge:' . (int)$chargeId;
                $ok = $tw->debitIfEnough(
                    tenantId: $tenantId,
                    amount: (float)$amount,
                    refType: 'partner_daily_charges',
                    refId: (int)$chargeId,
                    externalRef: $extTenant,
                    notes: 'Settlement diario (partner-mode)',
                    currency: $currency
                );

                // Si aquí falla, NO queremos que el partner quede debitado sin settlement: rollback
                if (!$ok) {
                    throw new \RuntimeException("TenantWallet insuficiente para settlement (charge_id={$chargeId}).");
                }

               DB::table('partner_daily_charges')->where('id', $chargeId)->update([
                  'paid_amount'   => $amount,
                  'unpaid_amount' => 0,
                  'settled_at'    => now(),
                  'updated_at'    => now(),
                ]);


                $totals['partners']++;
                $totals['amount'] += $amount;
            });
        }

        return ['ok' => true, 'date' => $chargeDate, 'daily_rate' => $rate, 'totals' => $totals];
    }

    /**
     * Estado de bloqueo por gracia SIN columnas nuevas:
     * - si existe cualquier cargo NO settleado con charge_date <= hoy - GRACE_DAYS => blocked
     * - si hay no-settleados recientes => grace
     * - si no => ok
     */
   public function partnerGateState(int $tenantId, int $partnerId, ?Carbon $now = null): array
{
    $tenant   = Tenant::with('billingProfile')->findOrFail($tenantId);
    $localNow = $this->tenantTzNow($tenant, $now);
    $today    = $localNow->copy()->startOfDay();

    $oldestUnpaid = DB::table('partner_daily_charges')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $partnerId)
        ->whereNull('settled_at')
        ->where('amount', '>', 0)
        ->min('charge_date'); // YYYY-MM-DD

    if (!$oldestUnpaid) {
        return [
            'state' => 'ok',
            'since' => null,
            'today' => $today->toDateString(),
            'days_past_due' => 0,
            'grace_left' => $this->GRACE_DAYS,
        ];
    }

    $since = Carbon::parse($oldestUnpaid, $tenant->timezone ?: config('app.timezone'))->startOfDay();
    $daysPastDue = $since->diffInDays($today); // 0 si es hoy, 1 si fue ayer, etc.

    // Política: 1..5 => grace, 6+ => blocked
    if ($daysPastDue >= ($this->GRACE_DAYS + 1)) {
        return [
            'state' => 'blocked',
            'since' => $since->toDateString(),
            'today' => $today->toDateString(),
            'days_past_due' => $daysPastDue,
            'grace_left' => 0,
        ];
    }

    // grace incluye daysPastDue=0 también (si hoy ya quedó pendiente)
    $graceLeft = max(0, $this->GRACE_DAYS - $daysPastDue);

    return [
        'state' => 'grace',
        'since' => $since->toDateString(),
        'today' => $today->toDateString(),
        'days_past_due' => $daysPastDue,
        'grace_left' => $graceLeft,
    ];
}


public function settleOutstandingChargesForPartner(int $tenantId, int $partnerId, ?Carbon $now = null, int $limit = 31): array
{
    $tenant   = Tenant::with('billingProfile')->findOrFail($tenantId);
    $localNow = $this->tenantTzNow($tenant, $now);

    /** @var TenantWalletService $tw */
    $tw = app(TenantWalletService::class);
    $tw->ensureWallet($tenantId);

    $settled = 0;
    $sum     = 0.0;

    DB::transaction(function () use ($tenantId, $partnerId, $tenant, $localNow, $tw, $limit, &$settled, &$sum) {

        // Lock wallet partner
        $pw = \App\Models\PartnerWallet::where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->lockForUpdate()
            ->first();

        if (!$pw) {
            $pw = PartnerWalletService::ensureWallet($tenantId, $partnerId);
            $pw = \App\Models\PartnerWallet::where('id', $pw->id)->lockForUpdate()->first();
        }

        $currency = strtoupper((string)($pw->currency ?? 'MXN'));

        // Tomar pendientes más viejos primero
        $charges = DB::table('partner_daily_charges')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->whereNull('settled_at')
            ->where('amount', '>', 0)
            ->orderBy('charge_date', 'asc')
            ->limit(max(1, $limit))
            ->lockForUpdate()
            ->get();

        foreach ($charges as $c) {
            $amount = (float)$c->amount;

            // Si no alcanza saldo partner, paramos (no “a medias”)
            $bal = (float)$pw->balance;
            if ($bal + 1e-9 < $amount) {
                break;
            }

            // 1) Debitar partner (idempotente por external_ref)
            $extPartner = 'partner_daily_charge:' . (int)$c->id;

            PartnerWalletService::debit(
                tenantId: $tenantId,
                partnerId: $partnerId,
                amount: number_format($amount, 2, '.', ''),
                currency: $currency,
                type: 'billing',
                refType: 'partner_daily_charges',
                refId: (int)$c->id,
                externalRef: $extPartner,
                notes: 'Regularización cargo diario pendiente (partner-mode)',
                meta: ['charge_date' => (string)$c->charge_date]
            );

            // refrescar balance (ya lo actualizó debit, pero tenemos el modelo lockeado)
            $pw->refresh();

            // 2) Settlement tenant (idempotente por external_ref)
            $extTenant = 'settlement_partner_daily_charge:' . (int)$c->id;

            $ok = $tw->debitIfEnough(
                tenantId: $tenantId,
                amount: $amount,
                refType: 'partner_daily_charges',
                refId: (int)$c->id,
                externalRef: $extTenant,
                notes: 'Settlement regularización (partner-mode)',
                currency: $currency
            );

            if (!$ok) {
                // rollback total: no queremos partner debitado sin settlement
                throw new \RuntimeException("TenantWallet insuficiente para settlement (charge_id={$c->id}).");
            }

            // 3) Marcar charge como settled y cuadrar paid/unpaid
            DB::table('partner_daily_charges')->where('id', (int)$c->id)->update([
                'paid_amount'   => number_format($amount, 2, '.', ''),
                'unpaid_amount' => number_format(0, 2, '.', ''),
                'settled_at'    => now(),
                'updated_at'    => now(),
            ]);

            $settled++;
            $sum += $amount;
        }
    });

    return [
        'ok' => true,
        'tenant_id' => $tenantId,
        'partner_id' => $partnerId,
        'settled_count' => $settled,
        'settled_amount' => round($sum, 2),
        'as_of' => $localNow->toDateTimeString(),
    ];
}


public function forecastPartner(
    int $tenantId,
    int $partnerId,
    ?Carbon $now = null
): array {
    $tenant   = Tenant::with('billingProfile')->findOrFail($tenantId);
    $localNow = $this->tenantTzNow($tenant, $now)->startOfDay();

    $wallet = PartnerWalletService::ensureWallet($tenantId, $partnerId);
    $balanceNow = (float)($wallet->balance ?? 0);

    // Vehículos activos hoy (en TZ del tenant)
    $vehicles = $this->activePartnerVehiclesOnDate($tenantId, $partnerId, $localNow);

    // Mes actual
    $daysInThisMonth = $this->daysInMonth($localNow);
    $dailyRateThis   = $this->dailyRateForTenant($tenant, $localNow);
    $dailyCostThis   = round($vehicles * $dailyRateThis, 2);

    $endOfMonth = $localNow->copy()->endOfMonth()->startOfDay();
    $daysLeftInclusive = $localNow->diffInDays($endOfMonth) + 1;

    $remainingCostEst = round($dailyCostThis * $daysLeftInclusive, 2);
    $endMonthBalanceEst = round($balanceNow - $remainingCostEst, 2);

    // Siguiente mes real
    $nextMonthDate = $localNow->copy()->addMonthNoOverflow()->startOfMonth();
    $daysNextMonth = $this->daysInMonth($nextMonthDate);
    $dailyRateNext = round(((float)($tenant->billingProfile->price_per_vehicle ?? 0)) / max(1, $daysNextMonth), 4);

    $nextMonthCostEst = round($vehicles * $dailyRateNext * $daysNextMonth, 2);

    $usableEndBalance = max(0, $endMonthBalanceEst);
    $recommendedTopup = round(max(0, $nextMonthCostEst - $usableEndBalance), 2);

    return [
        'currency' => $wallet->currency ?? 'MXN',

        'balance_now' => round($balanceNow, 2),
        'vehicles_today' => (int)$vehicles,

        'today' => $localNow->toDateString(),

        // Mes actual
        'daily_rate_this_month' => round($dailyRateThis, 4),
        'daily_cost_est' => $dailyCostThis,
        'days_left_in_month' => (int)$daysLeftInclusive,
        'remaining_cost_est' => $remainingCostEst,
        'end_month_balance_est' => $endMonthBalanceEst,

        // Siguiente mes
        'next_month_start' => $nextMonthDate->toDateString(),
        'days_next_month' => (int)$daysNextMonth,
        'daily_rate_next_month' => $dailyRateNext,
        'next_month_cost_est' => $nextMonthCostEst,

        // Acción sugerida
        'recommended_topup_for_next_month' => $recommendedTopup,
    ];
}


}