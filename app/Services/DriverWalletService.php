<?php

namespace App\Services;

use App\Models\DriverWallet;
use App\Models\DriverWalletMovement;
use App\Models\Ride;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DriverWalletService
{
    /**
     * Manejar el evento de "viaje terminado" para el tenant global.
     * - Calcula comisión según tenant_billing_profiles.
     * - Descuenta del wallet del driver.
     * - Registra movimiento.
     */
    public function handleRideFinished(Ride $ride): void
    {
        // Si no tiene driver, no hacemos nada
        if (!$ride->driver_id || !$ride->tenant_id) {
            return;
        }

        /** @var Tenant $tenant */
        $tenant = $ride->tenant;  // asumiendo relación tenant() en Ride

        // Solo procesar si es el tenant global
        $globalTenantId = (int) config('cabcontrol.global_tenant_id', 100);
        if ((int) $ride->tenant_id !== $globalTenantId) {
            // Los tenants normales podrán tener reportes, pero no wallet.
            return;
        }

        // Debe tener perfil con billing_model = commission
        $profile = $tenant->billingProfile;
        if (!$profile || $profile->billing_model !== 'commission') {
            return;
        }

        // Monto base del viaje: agreed_amount (bid) o total_amount
        $baseAmount = $ride->agreed_amount ?? $ride->total_amount ?? 0;
        if ($baseAmount <= 0) {
            // Nada que cobrar
            return;
        }

        // Porcentaje de comisión
        $percent = $profile->commission_percent ?? 0;
        if ($percent <= 0) {
            return;
        }

        // Calculamos comisión
        $commission = round($baseAmount * $percent / 100, 2);
        if ($commission <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($ride, $tenant, $commission, $baseAmount, $percent) {
                // Obtenemos o creamos el wallet del driver
                /** @var DriverWallet $wallet */
                $wallet = DriverWallet::lockForUpdate()->firstOrCreate(
                    ['driver_id' => $ride->driver_id],
                    [
                        'tenant_id'   => $tenant->id,
                        'balance'     => 0.00,
                        'status'      => 'active',
                        'min_balance' => 0.00,
                    ]
                );

                // Nuevo saldo
                $newBalance = (float) $wallet->balance - $commission;

                // Registramos movimiento
                $movement = new DriverWalletMovement();
                $movement->driver_id     = $wallet->driver_id;
                $movement->tenant_id     = $wallet->tenant_id;
                $movement->ride_id       = $ride->id;
                $movement->type          = 'commission';
                $movement->direction     = 'debit';
                $movement->amount        = $commission;
                $movement->balance_after = $newBalance;
                $movement->description   = sprintf(
                    'Comisión %s%% sobre viaje #%d (%.2f)',
                    $percent,
                    $ride->id,
                    $baseAmount
                );
                $movement->meta = [
                    'ride_amount'        => $baseAmount,
                    'commission_percent' => $percent,
                    'tenant_name'        => $tenant->name,
                ];
                $movement->created_at = now();
                $movement->save();

                // Actualizamos wallet
                $wallet->balance = $newBalance;

                // Si el saldo cayó por debajo del min_balance, lo bloqueamos
                if ($wallet->balance < $wallet->min_balance) {
                    $wallet->status = 'blocked';
                }

                $wallet->save();
            });
        } catch (Throwable $e) {
            // No tiramos la app, sólo logeamos el error para revisar después
            Log::error('Error al cobrar comisión de wallet de driver', [
                'ride_id' => $ride->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recargar saldo manualmente (por nosotros o por un panel).
     */
    public function topup(int $driverId, int $tenantId, float $amount, string $description = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($driverId, $tenantId, $amount, $description) {
            $wallet = DriverWallet::lockForUpdate()->firstOrCreate(
                ['driver_id' => $driverId],
                [
                    'tenant_id'   => $tenantId,
                    'balance'     => 0.00,
                    'status'      => 'active',
                    'min_balance' => 0.00,
                ]
            );

            $newBalance = (float) $wallet->balance + $amount;

            $mov = new DriverWalletMovement();
            $mov->driver_id     = $driverId;
            $mov->tenant_id     = $tenantId;
            $mov->ride_id       = null;
            $mov->type          = 'topup';
            $mov->direction     = 'credit';
            $mov->amount        = $amount;
            $mov->balance_after = $newBalance;
            $mov->description   = $description ?: 'Recarga manual de saldo';
            $mov->meta          = null;
            $mov->created_at    = now();
            $mov->save();

            $wallet->balance = $newBalance;

            // Si estaba bloqueado pero ya superó min_balance, lo reactivamos
            if ($wallet->status === 'blocked' && $wallet->balance >= $wallet->min_balance) {
                $wallet->status = 'active';
            }

            $wallet->save();
        });
    }
}
