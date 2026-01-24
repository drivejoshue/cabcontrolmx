<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\Carbon;

class PartnerBillingUIService
{
    /**
     * Precio mensual por vehículo para partner-network.  NO COBRA  SOLO ES UI 
     * Preferencia:
     * 1) tenant->billingProfile->price_per_vehicle (si existe)
     * 2) config('orbanamx.partner_price_per_vehicle', 299)
     */
    public static function pricePerVehicle(Tenant $tenant): float
    {
        $p = $tenant->billingProfile;
        if ($p && is_numeric($p->price_per_vehicle) && (float)$p->price_per_vehicle > 0) {
            return round((float)$p->price_per_vehicle, 2);
        }
        return (float) config('orbanamx.partner_price_per_vehicle', 299.00);
    }

    /**
     * Vehículos activos actualmente asignados al partner.
     */
    public static function activePartnerVehicles(int $tenantId, int $partnerId)
    {
        return Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('active', 1)
            ->whereNull('partner_left_at');
    }

    /**
     * Retorna el "inicio" para prorrateo del vehículo (en TZ tenant):
     * - partner_assigned_at si existe
     * - si no, created_at
     */
    public static function vehicleStartAtForProration(Vehicle $v, string $tz): Carbon
    {
        if (!empty($v->partner_assigned_at)) {
            // partner_assigned_at es DATETIME sin TZ => interpretarlo en TZ tenant
            return Carbon::parse($v->partner_assigned_at, $tz)->startOfDay();
        }

        // created_at es timestamp; Laravel lo parsea como Carbon
        // si viene UTC, lo llevamos a TZ tenant para consistencia de corte visual
        return Carbon::parse($v->created_at)->setTimezone($tz)->startOfDay();
    }

    /**
     * Resumen del mes actual para UI:
     * - base_prorated_total: total del mes actual (desde start de cada vehiculo hasta fin de mes)
     * - consumed_to_date_total: consumido hasta hoy (desde start hasta hoy)
     * - remaining_total: base - consumed
     * - daily_rate_estimated: ritmo diario estimado (ppv/dias_mes * count activos hoy)
     */
    public static function partnerMonthSummary(Tenant $tenant, int $partnerId, ?Carbon $now = null): array
    {
        $tz = $tenant->timezone ?: 'America/Mexico_City';
        $now = ($now ?: Carbon::now())->setTimezone($tz);

        $monthStart = $now->copy()->startOfMonth()->startOfDay();
        $monthEnd   = $now->copy()->endOfMonth()->startOfDay(); // usar startOfDay para conteo de dias
        $today      = $now->copy()->startOfDay();

        $daysInMonth = (int) $now->daysInMonth;

        $ppv = self::pricePerVehicle($tenant);
        $daily = $daysInMonth > 0 ? ($ppv / $daysInMonth) : 0.0;

        $vehicles = self::activePartnerVehicles((int)$tenant->id, $partnerId)->get();

        $baseTotal = 0.0;
        $consumedTotal = 0.0;

        $items = [];

        foreach ($vehicles as $v) {
            $startAt = self::vehicleStartAtForProration($v, $tz);

            // Recortar al mes actual
            $from = $startAt->copy()->max($monthStart);
            if ($from->gt($monthEnd)) {
                // activado después de fin de mes (caso raro)
                continue;
            }

            // Base del mes: from -> monthEnd (inclusive)
            $baseDays = $from->diffInDays($monthEnd) + 1;

            // Consumido a hoy: from -> min(today, monthEnd) (inclusive)
            $toConsumed = $today->copy()->min($monthEnd);
            $consumedDays = $from->gt($toConsumed) ? 0 : ($from->diffInDays($toConsumed) + 1);

            $base = round($daily * $baseDays, 2);
            $consumed = round($daily * $consumedDays, 2);
            $remaining = round(max(0, $base - $consumed), 2);

            $baseTotal += $base;
            $consumedTotal += $consumed;

            $items[] = [
                'vehicle_id' => (int)$v->id,
                'economico' => (string)$v->economico,
                'plate' => (string)$v->plate,
                'start_at' => $from->toDateString(),
                'base' => $base,
                'consumed' => $consumed,
                'remaining' => $remaining,
            ];
        }

        $baseTotal = round($baseTotal, 2);
        $consumedTotal = round($consumedTotal, 2);
        $remainingTotal = round(max(0, $baseTotal - $consumedTotal), 2);

        // Ritmo diario estimado: count de vehículos activos HOY * daily
        $dailyRate = round(count($vehicles) * $daily, 2);

        return [
            'tz' => $tz,
            'currency' => 'MXN',
            'ppv' => round($ppv, 2),
            'days_in_month' => $daysInMonth,
            'period_start' => $monthStart->toDateString(),
            'period_end' => $now->copy()->endOfMonth()->toDateString(),
            'vehicles_count' => count($vehicles),
            'base_prorated_total' => $baseTotal,
            'consumed_to_date_total' => $consumedTotal,
            'remaining_total' => $remainingTotal,
            'daily_rate_estimated' => $dailyRate,
            'items' => $items,
        ];
    }

    /**
     * Cuánto saldo mínimo requiere HOY para asignar/activar un NUEVO vehículo:
     * prorrateo desde hoy hasta fin de mes (inclusive).
     */
    public static function requiredToAddVehicleToday(Tenant $tenant, ?Carbon $now = null): array
    {
        $tz = $tenant->timezone ?: 'America/Mexico_City';
        $now = ($now ?: Carbon::now())->setTimezone($tz);

        $today = $now->copy()->startOfDay();
        $monthEnd = $now->copy()->endOfMonth()->startOfDay();
        $daysInMonth = (int) $now->daysInMonth;

        $ppv = self::pricePerVehicle($tenant);
        $daily = $daysInMonth > 0 ? ($ppv / $daysInMonth) : 0.0;

        if ($today->gt($monthEnd)) {
            return [
                'required_amount' => 0.0,
                'currency' => 'MXN',
                'label' => 'Mes concluido',
            ];
        }

        $daysRemaining = $today->diffInDays($monthEnd) + 1;
        $required = round($daily * $daysRemaining, 2);

        return [
            'required_amount' => $required,
            'currency' => 'MXN',
            'label' => 'Recarga minima para activar hoy (prorrateo a fin de mes)',
            'days_remaining' => $daysRemaining,
            'daily' => round($daily, 2),
            'period_start' => $today->toDateString(),
            'period_end' => $now->copy()->endOfMonth()->toDateString(),
        ];
    }


     public static function uiState(int $tenantId, int $partnerId, float $balance, ?Carbon $now = null): array
    {
        $tenant = Tenant::query()->with('billingProfile')->findOrFail($tenantId);

        $summary = self::partnerMonthSummary($tenant, $partnerId, $now);
        $requiredToAdd = self::requiredToAddVehicleToday($tenant, $now);

        $reqAmt = (float)($requiredToAdd['required_amount'] ?? 0.0);
        $missing = round(max(0, $reqAmt - $balance), 2);
        $canAdd = ($missing <= 0.00001);

        $tz = $summary['tz'] ?? ($tenant->timezone ?: 'America/Mexico_City');
        $nowTz = ($now ?: Carbon::now())->setTimezone($tz);

        // Corte informativo (modelo: corte día 1)
        $nextCut = $nowTz->copy()->addMonthNoOverflow()->startOfMonth()->toDateString();

        return [
            'billing_model' => 'partner_network',
            'currency' => $summary['currency'] ?? 'MXN',
            'tz' => $tz,

            // saldo
            'balance' => round($balance, 2),

            // consumo dinámico (informativo)
            'summary' => $summary,

            // gate para activar/asignar vehículo hoy (NO bloquear tenant, solo impedir crecer deuda)
            'required_to_add_vehicle_today' => $requiredToAdd,
            'can_add_vehicle_today' => $canAdd,
            'missing_to_add_vehicle_today' => $missing,

            // texto estable
            'note' => 'El “consumo” es informativo. El cobro real del tenant es por corte mensual (día 1).',
            'next_cut_date' => $nextCut,
        ];
    }
}
