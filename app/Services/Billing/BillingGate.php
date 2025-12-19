<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantInvoice;
use Carbon\Carbon;

class BillingGate
{
    // tolerancia después de due_date (en días)
    public const GRACE_DAYS = 5;

    /**
     * Determina si el tenant puede operar (dispatch/driver/passenger).
     * Retorna un array con:
     *  - allowed (bool)
     *  - code (string)
     *  - message (string)
     */
    public function decisionForTenant(Tenant $tenant): array
    {
        /** @var TenantBillingProfile|null $p */
        $p = $tenant->billingProfile;

        if (!$p) {
            return [false, 'no_profile', 'Central sin perfil de facturación.'];
        }

        // Commission (Orbana Global) no se bloquea aquí
        if ($p->billing_model !== 'per_vehicle') {
            return [true, 'ok_commission', ''];
        }

        $status = strtolower((string)$p->status);
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        // paused/canceled => bloqueo
        if (in_array($status, ['paused', 'canceled'], true)) {
            return [false, 'blocked_status', 'Central suspendida por facturación.'];
        }

        // trial => permitido mientras no expire; si expira sin aceptar => bloqueo
        if ($status === 'trial') {
            if ($p->trial_ends_at) {
                $trialEnd = Carbon::parse($p->trial_ends_at)->endOfDay();
                if ($now->gt($trialEnd) && empty($p->accepted_terms_at)) {
                    return [false, 'blocked_trial_expired', 'Tu periodo de prueba terminó. Acepta para continuar.'];
                }
            }
            return [true, 'ok_trial', ''];
        }

        // active => verificar overdue (invoice pending vencida + gracia)
        if ($status === 'active') {
            $inv = TenantInvoice::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->orderByDesc('due_date')
                ->first();

            if ($inv && $inv->due_date) {
                $due = Carbon::parse($inv->due_date)->startOfDay();
                $blockDate = $due->copy()->addDays(self::GRACE_DAYS);

                if ($today->gt($blockDate)) {
                    return [false, 'blocked_overdue', 'Central suspendida: pago vencido.'];
                }
            }

            return [true, 'ok_active', ''];
        }

        return [false, 'blocked_unknown', 'Estado de facturación inválido.'];
    }
}
