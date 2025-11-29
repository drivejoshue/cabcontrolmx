<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantInvoice;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TenantBillingService
{
    /**
     * Verifica si el tenant puede registrar un nuevo vehículo.
     * Aplica:
     *  - Trial: 5 vehículos por 14 días (trial_vehicles / trial_ends_at).
     *  - Límite de plan (max_vehicles) si se usa.
     */
    public function canRegisterNewVehicle(Tenant $tenant): array
    {
        /** @var TenantBillingProfile|null $profile */
        $profile = $tenant->billingProfile;

        if (!$profile) {
            return [false, 'El tenant no tiene perfil de facturación configurado.'];
        }

        $now = Carbon::now();

        // Vehículos activos actuales
        $activeVehicles = Vehicle::where('tenant_id', $tenant->id)
            ->where('active', 1)
            ->count();

        // Modo TRIAL
        if ($profile->status === 'trial') {
            // Si existe fecha fin de trial y ya pasó
            if ($profile->trial_ends_at && $now->gt($profile->trial_ends_at)) {
                return [false, 'Tu periodo de prueba ha finalizado. Contacta a soporte para activar tu plan.'];
            }

            $maxTrialVehicles = $profile->trial_vehicles ?? 5;
            if ($activeVehicles >= $maxTrialVehicles) {
                return [false, "En el periodo de prueba puedes usar hasta {$maxTrialVehicles} vehículos."];
            }

            return [true, null];
        }

        // Modo ACTIVE
        if ($profile->status === 'active') {
            if ($profile->max_vehicles !== null && $activeVehicles >= $profile->max_vehicles) {
                return [false, "Has alcanzado el límite de {$profile->max_vehicles} vehículos en tu plan."];
            }

            return [true, null];
        }

        // Pausado / cancelado
        if (in_array($profile->status, ['paused', 'canceled'], true)) {
            return [false, 'Tu plan está pausado o cancelado. No puedes registrar nuevos vehículos.'];
        }

        return [false, 'Estado de facturación no válido. Contacta a soporte.'];
    }

    /**
     * Calcula el periodo de facturación en base al día de corte.
     *
     * Regla:
     *  - period_end  = último día de corte <= cutoffDate
     *  - period_start = día siguiente al corte anterior
     *
     * Ejemplo: invoice_day = 15
     *  - cutoffDate = 2025-03-15 -> period_end = 2025-03-15, period_start = 2025-02-16
     *  - cutoffDate = 2025-03-20 -> sigue usando periodo que termina 15/03 pero normalmente
     *    solo deberíamos facturar cuando cutoffDate->day == invoice_day.
     */
    public function calculateBillingPeriod(TenantBillingProfile $profile, Carbon $cutoffDate): array
    {
        $invoiceDay = $profile->invoice_day ?: 1;

        $periodEnd = $cutoffDate->copy()->day($invoiceDay);
        if ($cutoffDate->day < $invoiceDay) {
            // Aún no llegamos al corte de este mes ⇒ usamos el corte del mes anterior
            $periodEnd->subMonth();
        }

        $periodStart = $periodEnd->copy()->subMonth()->addDay();

        return [$periodStart, $periodEnd];
    }

    /**
     * Genera (o reutiliza) una factura mensual para el tenant, basada en el
     * número de vehículos activos al momento del corte.
     *
     * - Idempotente: si ya existe factura para ese periodo, la devuelve.
     */
    public function generateMonthlyInvoice(Tenant $tenant, Carbon $cutoffDate): TenantInvoice
    {
        /** @var TenantBillingProfile|null $profile */
        $profile = $tenant->billingProfile;
        if (!$profile) {
            throw new \RuntimeException("Tenant {$tenant->id} no tiene perfil de facturación.");
        }

        // Sólo tiene sentido si billing_model = per_vehicle
        if ($profile->billing_model !== 'per_vehicle') {
            throw new \RuntimeException("Tenant {$tenant->id} no usa modelo per_vehicle.");
        }

        [$periodStart, $periodEnd] = $this->calculateBillingPeriod($profile, $cutoffDate);

        // ¿Ya existe factura para este periodo?
        $existing = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        // Vehículos activos al momento del corte
        $activeVehicles = Vehicle::where('tenant_id', $tenant->id)
            ->where('active', 1)
            ->count();

        // Cálculo económico (versión simple TaxiCaller-style)
        $baseFee         = (float) $profile->base_monthly_fee;
        $included        = (int) $profile->included_vehicles;
        $pricePerVehicle = (float) $profile->price_per_vehicle;

        $extraVehicles = max(0, $activeVehicles - $included);
        $vehiclesFee   = $extraVehicles * $pricePerVehicle;
        $total         = $baseFee + $vehiclesFee;

        return DB::transaction(function () use (
            $tenant,
            $profile,
            $periodStart,
            $periodEnd,
            $cutoffDate,
            $activeVehicles,
            $baseFee,
            $vehiclesFee,
            $total
        ) {
            $invoice = new TenantInvoice();
            $invoice->tenant_id          = $tenant->id;
            $invoice->billing_profile_id = $profile->id;
            $invoice->period_start       = $periodStart->toDateString();
            $invoice->period_end         = $periodEnd->toDateString();
            $invoice->issue_date         = $cutoffDate->toDateString();
            $invoice->due_date           = $cutoffDate->copy()->addDays(7)->toDateString();
            $invoice->status             = 'pending';
            $invoice->vehicles_count     = $activeVehicles;
            $invoice->base_fee           = $baseFee;
            $invoice->vehicles_fee       = $vehiclesFee;
            $invoice->total              = $total;
            $invoice->currency           = 'MXN';
            $invoice->notes              = null;
            $invoice->save();

            // Actualizamos fechas en el profile (ayuda de tracking)
            $profile->last_invoice_date = $invoice->issue_date;
            $profile->next_invoice_date = $periodEnd->copy()->addMonth()->toDateString();
            $profile->save();

            return $invoice;
        });
    }
}
