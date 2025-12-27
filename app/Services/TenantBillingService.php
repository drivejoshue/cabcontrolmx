<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantInvoice;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\TenantWalletService;


class TenantBillingService
{
    private int $TRIAL_DAYS = 14;
    private int $PAY_GRACE_DAYS = 5;

    public function periodEomFor(Carbon $ref): array
    {
        $start = $ref->copy()->startOfMonth()->startOfDay();
        $end   = $ref->copy()->endOfMonth()->endOfDay();
        return [$start, $end];
    }

    public function trialEndsAt(TenantBillingProfile $p): ?Carbon
    {
        if (!empty($p->trial_ends_at)) return Carbon::parse($p->trial_ends_at)->endOfDay();
        if (!empty($p->created_at))   return Carbon::parse($p->created_at)->copy()->addDays($this->TRIAL_DAYS)->endOfDay();
        return null;
    }

    public function isTrialExpired(TenantBillingProfile $p, ?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now();
        $ends = $this->trialEndsAt($p);
        return (strtolower((string)$p->status) === 'trial') && $ends && $now->gt($ends);
    }

    public function activeVehiclesCount(int $tenantId): int
    {
        return Vehicle::where('tenant_id', $tenantId)->where('active', 1)->count();
    }

    public function monthlyAmountForVehicles(TenantBillingProfile $p, int $activeCount): float
    {
        $baseFee = (float)$p->base_monthly_fee;
        $included = (int)$p->included_vehicles;
        $ppv = (float)$p->price_per_vehicle;

        $extra = max(0, $activeCount - $included);
        return round($baseFee + ($extra * $ppv), 2);
    }

    public function prorateMonthly(float $monthlyAmount, Carbon $from, Carbon $to, Carbon $periodStart, Carbon $periodEnd): float
    {
        $daysInPeriod = $periodStart->diffInDays($periodEnd) + 1;
        $activeDays   = $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1;
        $ratio = $activeDays / max(1, $daysInPeriod);
        return round($monthlyAmount * $ratio, 2);
    }

    /**
     * POST-TRIAL:
     * - Genera invoice prorrateada (día siguiente a trial_end -> fin de mes).
     * - Suspende profile a paused hasta pagar.
     */

     public function canRegisterNewVehicle(Tenant $tenant): array
    {
        $profile = $tenant->billingProfile;
        if (!$profile) return [false, 'Sin perfil de facturación.'];

        if ($profile->billing_model !== 'per_vehicle') {
            return [true, null];
        }

        $activeVehicles = Vehicle::where('tenant_id', $tenant->id)->where('active', 1)->count();
        $status = strtolower((string)$profile->status);

        if ($status === 'trial') {
            if ($this->isTrialExpired($profile)) {
                return [false, 'Tu periodo de prueba terminó. Recarga tu cuenta para reactivar.'];
            }
            $maxTrial = (int)($profile->trial_vehicles ?? 5);
            if ($activeVehicles >= $maxTrial) {
                return [false, "En el trial puedes usar hasta {$maxTrial} vehículos."];
            }
            return [true, null];
        }

        if ($status === 'active') {
            if ($profile->max_vehicles !== null && $activeVehicles >= (int)$profile->max_vehicles) {
                return [false, "Límite de plan alcanzado ({$profile->max_vehicles})."];
            }
            return [true, null];
        }

        if (in_array($status, ['paused','canceled'], true)) {
            return [false, 'Plan pausado/cancelado.'];
        }

        return [false, 'Estado inválido.'];
    }



    public function ensurePostTrialActivationInvoice(Tenant $tenant, Carbon $now): ?TenantInvoice
    {
        $p = $tenant->billingProfile;
        if (!$p || $p->billing_model !== 'per_vehicle') return null;

        if (!$this->isTrialExpired($p, $now)) return null;

        [$mStart, $mEnd] = $this->periodEomFor($now);
        $trialEnds = $this->trialEndsAt($p);

        $from = $trialEnds ? $trialEnds->copy()->addDay()->startOfDay() : $now->copy()->startOfDay();
        if ($from->lt($mStart)) $from = $mStart->copy();

        $to = $mEnd->copy();

        if ($from->gt($to)) return null;

        $existing = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('period_start', $from->toDateString())
            ->where('period_end', $to->toDateString())
            ->whereIn('status', ['draft','pending','paid'])
            ->first();

        if ($existing) return $existing;

        $activeCount = $this->activeVehiclesCount($tenant->id);
        $monthly = $this->monthlyAmountForVehicles($p, $activeCount);
        $amount  = $this->prorateMonthly($monthly, $from, $to, $mStart, $mEnd);

        //$status = $p->accepted_terms_at ? 'pending' : 'draft';
        $status = 'pending';


        return DB::transaction(function () use ($tenant, $p, $now, $from, $to, $activeCount, $amount, $status) {
            $inv = new TenantInvoice();
            $inv->tenant_id = $tenant->id;
            $inv->billing_profile_id = $p->id;
            $inv->period_start = $from->toDateString();
            $inv->period_end   = $to->toDateString();
            $inv->issue_date   = $now->toDateString();
            $inv->due_date     = $now->copy()->addDays($this->PAY_GRACE_DAYS)->toDateString();
            $inv->status       = $status; // draft | pending
            $inv->vehicles_count = $activeCount;
            $inv->base_fee = 0.00;
            $inv->vehicles_fee = $amount;
            $inv->total = $amount;
            $inv->currency = 'MXN';
            $inv->notes = 'Activación post-trial (prorrateo a fin de mes).';
            $inv->save();

            // Suspender hasta pagar
            $p->status = 'paused';
            $p->suspended_at = now();
            $p->suspension_reason = 'trial_expired';
            $p->save();

            return $inv;
        });
    }

    /**
     * MES COMPLETO (PREPAGO): se corre el día 1.
     * - status=pending
     * - due_date = +PAY_GRACE_DAYS
     */
    public function generateMonthInvoicePrepaid(Tenant $tenant, Carbon $monthRef): TenantInvoice
    {
        $p = $tenant->billingProfile;
        if (!$p) throw new \RuntimeException("Tenant sin billing profile.");
        if ($p->billing_model !== 'per_vehicle') throw new \RuntimeException("No per_vehicle.");

        // Si está paused por falta de pago, igual puedes generar “próximo cargo estimado”,
        // pero NO conviene generar invoice mensual hasta reactivar.
        if (strtolower((string)$p->status) !== 'active') {
            throw new \RuntimeException("Billing profile no está activo.");
        }

        [$start, $end] = $this->periodEomFor($monthRef);

        $existing = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->whereIn('status', ['draft','pending','paid'])
            ->first();

        if ($existing) return $existing;

        $activeCount = $this->activeVehiclesCount($tenant->id);
        $monthly = $this->monthlyAmountForVehicles($p, $activeCount);

        return DB::transaction(function () use ($tenant, $p, $start, $end, $activeCount, $monthly) {
            $inv = new TenantInvoice();
            $inv->tenant_id = $tenant->id;
            $inv->billing_profile_id = $p->id;
            $inv->period_start = $start->toDateString();
            $inv->period_end   = $end->toDateString();
            $inv->issue_date   = $start->toDateString(); // día 1
            $inv->due_date     = $start->copy()->addDays($this->PAY_GRACE_DAYS)->toDateString();
            $inv->status       = 'pending';
            $inv->vehicles_count = $activeCount;

            // desglose simple (si quieres guardarlo fino: base_fee + vehicles_fee)
            $inv->base_fee = (float)$p->base_monthly_fee;
            $extra = max(0, $activeCount - (int)$p->included_vehicles);
            $inv->vehicles_fee = round($extra * (float)$p->price_per_vehicle, 2);
            $inv->total = $monthly;

            $inv->currency = 'MXN';
            $inv->notes = 'Cargo mensual por adelantado (wallet).';
            $inv->save();

            $p->last_invoice_date = $inv->issue_date;
            $p->next_invoice_date = Carbon::parse($end)->addMonth()->startOfMonth()->toDateString();
            $p->save();

            return $inv;
        });
    }

    /**
     * Estimado próximo cargo (para UI).
     * - Si trial: estimado del mes completo (pero NO se cobra)
     * - Si paused: estimado para reactivar (post-trial prorrateo si aplica)
     * - Si active: estimado del próximo mes (mes completo)
     */
    public function estimatedNextCharge(Tenant $tenant, Carbon $now): array
    {
        $p = $tenant->billingProfile;
        if (!$p || $p->billing_model !== 'per_vehicle') {
            return ['amount' => 0.0, 'label' => 'No aplica', 'period_start' => null, 'period_end' => null];
        }

        [$mStart, $mEnd] = $this->periodEomFor($now);
        $activeCount = $this->activeVehiclesCount($tenant->id);
        $monthly = $this->monthlyAmountForVehicles($p, $activeCount);

        $st = strtolower((string)$p->status);

        // trial: solo informativo
        if ($st === 'trial') {
            return [
                'amount' => $monthly,
                'label' => 'Estimado mensual (se cobrará al finalizar trial)',
                'period_start' => $mStart->toDateString(),
                'period_end' => $mEnd->toDateString(),
            ];
        }

        // paused por trial vencido: prorrateo hasta fin de mes
        if ($st === 'paused' && $p->suspension_reason === 'trial_expired') {
            $trialEnds = $this->trialEndsAt($p);
            $from = $trialEnds ? $trialEnds->copy()->addDay()->startOfDay() : $now->copy()->startOfDay();
            if ($from->lt($mStart)) $from = $mStart->copy();
            $to = $mEnd->copy();

            $amount = $from->gt($to) ? 0.0 : $this->prorateMonthly($monthly, $from, $to, $mStart, $mEnd);

            return [
                'amount' => $amount,
                'label' => 'Recarga mínima para reactivar (prorrateo fin de mes)',
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ];
        }

        // active: próximo mes completo
        $next = $now->copy()->addMonth()->startOfMonth();
        [$nStart, $nEnd] = $this->periodEomFor($next);

        return [
            'amount' => $monthly,
            'label' => 'Próximo cargo mensual (prepago)',
            'period_start' => $nStart->toDateString(),
            'period_end' => $nEnd->toDateString(),
        ];
    }

    /**
     * Pagar invoice desde wallet (si alcanza).
     * - invoice.status debe ser pending
     * - si paga: invoice->paid y activa profile si estaba paused
     */
    public function payInvoiceFromWallet(
        TenantInvoice $invoice,
        TenantWalletService $wallet
    ): bool {
        if (!in_array($invoice->status, ['pending'], true)) return false;

        $tenantId = (int)$invoice->tenant_id;
        $amount   = (float)$invoice->total;

        $ok = $wallet->debitIfEnough(
            $tenantId,
            $amount,
            'tenant_invoice',
            (int)$invoice->id,
            null,
            'Pago de factura desde wallet'
        );

        if (!$ok) return false;

        DB::transaction(function () use ($invoice) {
            $invoice->status = 'paid';
            $invoice->save();

            // Re-activar tenant si estaba suspendido
            $p = $invoice->billingProfile;
            if ($p && strtolower((string)$p->status) !== 'active') {
                $p->status = 'active';
                $p->suspended_at = null;
                $p->suspension_reason = null;
                $p->save();
            }
        });

        return true;
    }



public function requiredBalanceToFinishMonth(Tenant $tenant, $date): array
{
    $now = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
    $p = $tenant->billingProfile;

    if (!$p || ($p->billing_model ?? 'per_vehicle') !== 'per_vehicle') {
        return [
            'required_amount' => 0.0,
            'currency' => 'MXN',
            'label' => 'No aplica',
            'reason' => 'not_per_vehicle',
        ];
    }

    [$mStart, $mEnd] = $this->periodEomFor($now);

    $currency = 'MXN';

    $status = strtolower((string)$p->status);

    // Trial vigente => no requerido (pero sugerimos estimado mensual)
    if ($status === 'trial' && !$this->isTrialExpired($p, $now)) {
        $activeCount = Vehicle::where('tenant_id', $tenant->id)->where('active', 1)->count();
        $monthly = round($this->monthlyAmountForVehicles($p, $activeCount), 2);

        return [
            'required_amount' => 0.0,
            'currency' => $currency,
            'label' => 'Trial activo (sin cobro)',
            'reason' => 'trial_active',
            'estimated_monthly' => $monthly,
            'estimated_label' => 'Estimado mensual (se cobrará al finalizar trial)',
        ];
    }

    // 1) Si existe invoice NO pagado que toca el mes actual, ese manda
    // (activación post-trial prorrateada o mensual adelantado en pending)
    $unpaid = TenantInvoice::where('tenant_id', $tenant->id)
        ->whereIn('status', ['draft','pending','overdue'])
        ->whereDate('period_end', '>=', $mStart->toDateString())
        ->whereDate('period_start', '<=', $mEnd->toDateString())
        ->orderByDesc('issue_date')
        ->orderByDesc('id')
        ->first();

    if ($unpaid) {
        return [
            'required_amount' => round((float)$unpaid->total, 2),
            'currency' => $unpaid->currency ?? $currency,
            'label' => 'Saldo requerido para continuar',
            'reason' => 'unpaid_invoice',
            'invoice_id' => $unpaid->id,
            'period_start' => (string)$unpaid->period_start,
            'period_end' => (string)$unpaid->period_end,
            'status' => (string)$unpaid->status,
        ];
    }

    // 2) Si está en trial_expired/paused por trial_expired y todavía no se creó invoice,
    // el "requerido" es prorrateo desde mañana (o desde hoy) hasta fin de mes
    // (esto permite middleware funcionar incluso si el cron no corrió)
    if ($status === 'trial' && $this->isTrialExpired($p, $now)) {
        $trialEnds = $this->trialEndsAt($p);
        $from = $trialEnds ? $trialEnds->copy()->addDay()->startOfDay() : $now->copy()->startOfDay();
        if ($from->lt($mStart)) $from = $mStart->copy();
        $to = $mEnd->copy();

        if ($from->gt($to)) {
            return [
                'required_amount' => 0.0,
                'currency' => $currency,
                'label' => 'Sin saldo requerido',
                'reason' => 'month_passed',
            ];
        }

        $activeCount = Vehicle::where('tenant_id', $tenant->id)->where('active', 1)->count();
        $monthly = $this->monthlyAmountForVehicles($p, $activeCount);
        $prorated = $this->prorateMonthly($monthly, $from, $to, $mStart, $mEnd);


        return [
            'required_amount' => round($prorated, 2),
            'currency' => $currency,
            'label' => 'Activación post-trial (prorrateo a fin de mes)',
            'reason' => 'trial_expired_prorated',
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
        ];
    }

    // 3) Active sin invoice pendiente del mes => ya está cubierto
    return [
        'required_amount' => 0.0,
        'currency' => $currency,
        'label' => 'Saldo suficiente',
        'reason' => 'covered',
    ];
}

/**
 * Revalida el estado del tenant según:
 * - saldo en wallet
 * - requerimiento del mes actual
 *
 * Se llama desde:
 * - Webhook Mercado Pago
 * - Cron diario
 * - Acciones críticas (login, middleware)
 */
public function recheckTenantBillingState(
    Tenant $tenant,
    TenantWalletService $wallet,
    ?Carbon $date = null
): void {
    $now = $date ?: Carbon::now();
    $p = $tenant->billingProfile;

    if (!$p || ($p->billing_model ?? '') !== 'per_vehicle') {
        return;
    }

    // Trial vigente no bloquea aquí
    $profileStatus = strtolower((string)$p->status);
    if ($profileStatus === 'trial' && !$this->isTrialExpired($p, $now)) {
        return;
    }

    // UI State “canónico”
    $ui = $this->billingUiState($tenant, $wallet, $now);
    $state = $ui['billing_state'] ?? 'ok';

    // Ubicar factura relevante (si existe)
    $inv = $this->findCurrentUnpaidInvoice($tenant, $now);

    // 1) Si no hay deuda del mes => activar
    if ($state === 'ok' || $state === 'trial') {
        if (strtolower((string)$p->status) !== 'active') {
            $p->status = 'active';
            $p->suspended_at = null;
            $p->suspension_reason = null;
            $p->save();
        }
        return;
    }

    // 2) Draft: requiere términos. No pausamos automáticamente aquí.
    //    (La UI debe empujar a "Aceptar términos".)
    if ($state === 'action_required') {
        // Si estaba paused por algo anterior, lo dejamos como está.
        // Opcional: podrías mantenerlo active pero con mensaje de acción requerida.
        return;
    }

    // 3) Pending dentro de gracia (aunque no alcance saldo) => NO pausar
    if ($state === 'pending' || $state === 'grace') {
        // Asegurar que no quede "paused" solo por saldo insuficiente dentro de gracia
        if (strtolower((string)$p->status) === 'paused' && ($p->suspension_reason ?? null) === 'insufficient_balance') {
            $p->status = 'active';
            $p->suspended_at = null;
            $p->suspension_reason = null;
            $p->save();
        }
        return;
    }

    // 4) Overdue => marcar invoice overdue (si aplica) y pausar
    if ($state === 'overdue') {
        if ($inv && strtolower((string)$inv->status) === 'pending') {
            // Marcar overdue al vencimiento (idempotente)
            $inv->status = 'overdue';
            $inv->save();
        }

        if (strtolower((string)$p->status) !== 'paused') {
            $p->status = 'paused';
            $p->suspended_at = now();
            $p->suspension_reason = 'overdue';
            $p->save();
        } else {
            // Si ya estaba paused, normalizamos reason si venía vacío
            if (empty($p->suspension_reason)) {
                $p->suspension_reason = 'overdue';
                $p->save();
            }
        }

        return;
    }

    // Fallback conservador: si algo raro ocurre, no tocar estado.
}


/**
 * Devuelve la "factura relevante" para bloqueo/estado:
 * - draft|pending|overdue
 * - que toque el mes actual (por period_start/period_end)
 */
private function findCurrentUnpaidInvoice(Tenant $tenant, Carbon $now): ?TenantInvoice
{
    [$mStart, $mEnd] = $this->periodEomFor($now);

    return TenantInvoice::where('tenant_id', $tenant->id)
        ->whereIn('status', ['draft','pending','overdue'])
        ->whereDate('period_end', '>=', $mStart->toDateString())
        ->whereDate('period_start', '<=', $mEnd->toDateString())
        ->orderByDesc('issue_date')
        ->orderByDesc('id')
        ->first();
}

/**
 * Construye un estado "limpio" para UI/SDK:
 * billing_state:
 * - ok
 * - trial
 * - action_required (draft por términos)
 * - pending (por pagar, dentro de gracia)
 * - grace (saldo insuficiente pero aún en gracia)
 * - overdue (venció)
 * - paused (bloqueado)
 */
public function billingUiState(
    Tenant $tenant,
    TenantWalletService $wallet,
    ?Carbon $date = null
): array {
    $now = $date ?: Carbon::now();
    $p = $tenant->billingProfile;

    if (!$p || ($p->billing_model ?? '') !== 'per_vehicle') {
        return [
            'billing_state' => 'ok',
            'billing_message' => null,
            'required_amount' => 0.0,
            'balance' => null,
            'currency' => 'MXN',
            'invoice_id' => null,
            'invoice_status' => null,
            'due_date' => null,
        ];
    }

    $balance = (float)($wallet->ensureWallet((int)$tenant->id)->balance ?? 0);
    $currency = 'MXN';

    // Trial vigente: no bloquea, solo informativo
    $st = strtolower((string)$p->status);
    if ($st === 'trial' && !$this->isTrialExpired($p, $now)) {
        $ends = $this->trialEndsAt($p);
        return [
            'billing_state' => 'trial',
            'billing_message' => $ends
                ? ('Trial activo. Termina el '.$ends->toDateString().'.')
                : 'Trial activo.',
            'required_amount' => 0.0,
            'balance' => $balance,
            'currency' => $currency,
            'invoice_id' => null,
            'invoice_status' => null,
            'due_date' => null,
        ];
    }

    // Factura abierta del mes actual (draft|pending|overdue)
    $inv = $this->findCurrentUnpaidInvoice($tenant, $now);

    if (!$inv) {
        return [
            'billing_state' => 'ok',
            'billing_message' => null,
            'required_amount' => 0.0,
            'balance' => $balance,
            'currency' => $currency,
            'invoice_id' => null,
            'invoice_status' => null,
            'due_date' => null,
        ];
    }

    $invStatus = strtolower((string)$inv->status);
    $due = !empty($inv->due_date) ? Carbon::parse($inv->due_date)->endOfDay() : null;
    $amount = round((float)($inv->total ?? 0), 2);
    $currency = $inv->currency ?: $currency;

    // Draft = falta aceptar términos (no debería bloquear “técnicamente”, pero sí requiere acción)
    if ($invStatus === 'draft') {
        return [
            'billing_state' => 'action_required',
            'billing_message' => 'Acepta términos para habilitar el cobro de tu factura y continuar operando.',
            'required_amount' => $amount,
            'balance' => $balance,
            'currency' => $currency,
            'invoice_id' => (int)$inv->id,
            'invoice_status' => $invStatus,
            'due_date' => $due?->toDateString(),
        ];
    }

    // Pending/Overdue: validar gracia
    $inGrace = $due ? $now->copy()->endOfDay()->lte($due) : true;

    if ($invStatus === 'pending') {
        if ($balance + 1e-9 >= $amount) {
            // Tiene saldo suficiente (el cron lo cobrará)
            return [
                'billing_state' => 'pending',
                'billing_message' => $due
                    ? ('Factura pendiente por $'.number_format($amount,2).' '.$currency.'. Vence el '.$due->toDateString().'.')
                    : ('Factura pendiente por $'.number_format($amount,2).' '.$currency.'.'),
                'required_amount' => $amount,
                'balance' => $balance,
                'currency' => $currency,
                'invoice_id' => (int)$inv->id,
                'invoice_status' => $invStatus,
                'due_date' => $due?->toDateString(),
            ];
        }

        // No alcanza
        if ($inGrace) {
            $faltante = max(0, round($amount - $balance, 2));
            return [
                'billing_state' => 'grace',
                'billing_message' => $due
                    ? ('Saldo insuficiente. Te faltan $'.number_format($faltante,2).' '.$currency.' para cubrir la factura antes del '.$due->toDateString().'.')
                    : ('Saldo insuficiente. Completa tu saldo para cubrir la factura.'),
                'required_amount' => $amount,
                'balance' => $balance,
                'currency' => $currency,
                'invoice_id' => (int)$inv->id,
                'invoice_status' => $invStatus,
                'due_date' => $due?->toDateString(),
            ];
        }

        // Ya venció pero aún está pending (lo convertiremos a overdue en recheck)
        return [
            'billing_state' => 'overdue',
            'billing_message' => $due
                ? ('Factura vencida desde el '.$due->toDateString().'. Recarga para reactivar.')
                : 'Factura vencida. Recarga para reactivar.',
            'required_amount' => $amount,
            'balance' => $balance,
            'currency' => $currency,
            'invoice_id' => (int)$inv->id,
            'invoice_status' => 'overdue',
            'due_date' => $due?->toDateString(),
        ];
    }

    // overdue
    if ($invStatus === 'overdue') {
        return [
            'billing_state' => 'overdue',
            'billing_message' => 'Factura vencida. Recarga para reactivar.',
            'required_amount' => $amount,
            'balance' => $balance,
            'currency' => $currency,
            'invoice_id' => (int)$inv->id,
            'invoice_status' => $invStatus,
            'due_date' => $due?->toDateString(),
        ];
    }

    // fallback
    return [
        'billing_state' => 'pending',
        'billing_message' => 'Estado de facturación pendiente.',
        'required_amount' => $amount,
        'balance' => $balance,
        'currency' => $currency,
        'invoice_id' => (int)$inv->id,
        'invoice_status' => $invStatus,
        'due_date' => $due?->toDateString(),
    ];
}



}
