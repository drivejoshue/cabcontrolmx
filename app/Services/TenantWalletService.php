<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantWalletService
{
    public function ensureWallet(int $tenantId): object
    {
        $w = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->first();
        if ($w) return $w;

        DB::table('tenant_wallets')->insert([
            'tenant_id'    => $tenantId,
            'balance'      => 0.00,
            'currency'     => 'MXN',
            'last_topup_at'=> null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return DB::table('tenant_wallets')->where('tenant_id', $tenantId)->first();
    }

    /**
     * Recarga confirmada (simulación o webhook “approved” en el futuro).
     * Crea movimiento type=topup y sube balance.
     */
    public function creditTopup(
        int $tenantId,
        float $amount,
        string $externalRef,
        ?string $notes = null,
        ?string $currency = 'MXN'
    ): void {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount inválido');
        }

        DB::transaction(function () use ($tenantId, $amount, $externalRef, $notes, $currency) {
            $this->ensureWallet($tenantId);

            // Evitar doble aplicación por external_ref (idempotencia simple)
            $exists = DB::table('tenant_wallet_movements')
                ->where('tenant_id', $tenantId)
                ->where('external_ref', $externalRef)
                ->exists();

            if ($exists) {
                return; // ya aplicada
            }

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance'       => DB::raw('balance + ' . $amount),
                    'currency'      => $currency ?? 'MXN',
                    'last_topup_at' => now(),
                    'updated_at'    => now(),
                ]);

            DB::table('tenant_wallet_movements')->insert([
                'tenant_id'    => $tenantId,
                'type'         => 'topup',
                'amount'       => $amount,
                'currency'     => $currency ?? 'MXN',
                'ref_type'     => null,
                'ref_id'       => null,
                'external_ref' => $externalRef,
                'notes'        => $notes,
                'created_at'   => now(),
            ]);
        });
    }

    /**
     * Debita (cobro) si hay saldo suficiente. Ideal para cobrar invoices.
     * type=debit, ref_type/ref_id apuntan a invoice.
     */
    public function debitIfEnough(
        int $tenantId,
        float $amount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $externalRef = null,
        ?string $notes = null,
        ?string $currency = 'MXN'
    ): bool {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) return true;

        return DB::transaction(function () use ($tenantId, $amount, $refType, $refId, $externalRef, $notes, $currency) {
            $this->ensureWallet($tenantId);

            // lock wallet row
            $w = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            $bal = (float)($w->balance ?? 0);

            if ($bal + 1e-9 < $amount) {
                return false;
            }

            // Idempotencia opcional por external_ref
            if ($externalRef) {
                $exists = DB::table('tenant_wallet_movements')
                    ->where('tenant_id', $tenantId)
                    ->where('external_ref', $externalRef)
                    ->exists();
                if ($exists) return true;
            }

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance'    => DB::raw('balance - ' . $amount),
                    'currency'   => $currency ?? 'MXN',
                    'updated_at' => now(),
                ]);

            DB::table('tenant_wallet_movements')->insert([
                'tenant_id'    => $tenantId,
                'type'         => 'debit',
                'amount'       => $amount,
                'currency'     => $currency ?? 'MXN',
                'ref_type'     => $refType,
                'ref_id'       => $refId,
                'external_ref' => $externalRef,
                'notes'        => $notes,
                'created_at'   => now(),
            ]);

            return true;
        });
    }

    /**
     * Crédito “no topup” (ajustes, bonificaciones).
     * type=credit
     */
    public function credit(
        int $tenantId,
        float $amount,
        ?string $refType = null,
        ?int $refId = null,
        ?string $externalRef = null,
        ?string $notes = null,
        ?string $currency = 'MXN'
    ): void {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) return;

        DB::transaction(function () use ($tenantId, $amount, $refType, $refId, $externalRef, $notes, $currency) {
            $this->ensureWallet($tenantId);

            if ($externalRef) {
                $exists = DB::table('tenant_wallet_movements')
                    ->where('tenant_id', $tenantId)
                    ->where('external_ref', $externalRef)
                    ->exists();
                if ($exists) return;
            }

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance'    => DB::raw('balance + ' . $amount),
                    'currency'   => $currency ?? 'MXN',
                    'updated_at' => now(),
                ]);

            DB::table('tenant_wallet_movements')->insert([
                'tenant_id'    => $tenantId,
                'type'         => 'credit',
                'amount'       => $amount,
                'currency'     => $currency ?? 'MXN',
                'ref_type'     => $refType,
                'ref_id'       => $refId,
                'external_ref' => $externalRef,
                'notes'        => $notes,
                'created_at'   => now(),
            ]);
        });
    }
}
