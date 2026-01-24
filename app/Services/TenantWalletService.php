<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * TenantWalletService
 *
 * Reglas clave:
 * - El wallet es la ÚNICA fuente de verdad financiera del tenant.
 * - Nunca se modifica balance sin crear un movimiento.
 * - Todas las operaciones son idempotentes por external_ref.
 * - Los tenants NO pueden acreditar manualmente.
 */
class TenantWalletService
{
    /**
     * Asegura que el tenant tenga wallet.
     * Se llama en todos los puntos de entrada.
     */
    public function ensureWallet(int $tenantId): object
    {
        $w = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->first();
        if ($w) return $w;

        DB::table('tenant_wallets')->insert([
            'tenant_id'     => $tenantId,
            'balance'       => 0.00,
            'currency'      => 'MXN',
            'last_topup_at' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return DB::table('tenant_wallets')->where('tenant_id', $tenantId)->first();
    }

    /**
     * Acredita una recarga confirmada (webhook MercadoPago).
     *
     * - type=topup
     * - Idempotente por external_ref
     * - DEVUELVE true si acreditó, false si ya existía
     */
    public function creditTopup(
        int $tenantId,
        float $amount,
        string $externalRef,
        ?string $notes = null,
        ?string $currency = 'MXN'
    ): bool {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount inválido');
        }

        return DB::transaction(function () use ($tenantId, $amount, $externalRef, $notes, $currency) {
            $this->ensureWallet($tenantId);

            // Evita doble acreditación
            $exists = DB::table('tenant_wallet_movements')
                ->where('tenant_id', $tenantId)
                ->where('external_ref', $externalRef)
                ->exists();

            if ($exists) return false;

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
                'external_ref' => $externalRef,
                'notes'        => $notes,
                'created_at'   => now(),
            ]);

            return true;
        });
    }

    /**
     * Debita saldo si hay balance suficiente.
     * Usado para cobrar invoices.
     *
     * - type=debit
     * - lockForUpdate evita race conditions
     * - external_ref debe ser único por cargo (ej: invoice:123)
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

            // Bloquea fila para evitar doble débito concurrente
            $w = DB::table('tenant_wallets')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            $balance = (float)($w->balance ?? 0);
            if ($balance + 1e-9 < $amount) {
                return false;
            }

            // Idempotencia por external_ref
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
                    'currency'   => $currency ,
                    'updated_at' => now(),
                ]);

            DB::table('tenant_wallet_movements')->insert([
                'tenant_id'    => $tenantId,
                'type'         => 'debit',
                'amount'       => $amount,
                'currency'     => $currency,
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
     * Crédito interno (ajustes, bonificaciones).
     * NO se usa para recargas de clientes.
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


    /**
     * Obtiene el balance actual del wallet del tenant.
     * - Siempre asegura existencia del wallet (ensureWallet)
     * - Solo lectura (NO crea movimientos)
     */
    public function getBalance(int $tenantId): float
    {
        $w = $this->ensureWallet($tenantId);
        return round((float)($w->balance ?? 0), 2);
    }

        /**
     * Obtiene el registro completo del wallet (objeto DB::first()).
     * Útil para UI (balance, currency, last_topup_at, etc.)
     */
    public function getWallet(int $tenantId): object
    {
        return $this->ensureWallet($tenantId);
    }

        /**
     * Moneda configurada en wallet (por si en el futuro soportas multi-moneda).
     */
    public function getCurrency(int $tenantId): string
    {
        $w = $this->ensureWallet($tenantId);
        return (string)($w->currency ?? 'MXN');
    }

    /**
 * Acredita una recarga tipo "topup" pero permitiendo ref_type/ref_id.
 * Útil para partner_topups, ajustes de entrada, etc.
 *
 * - type=topup
 * - Idempotente por external_ref
 * - DEVUELVE true si acreditó, false si ya existía
 */
public function creditTopupWithRef(
    int $tenantId,
    float $amount,
    string $externalRef,
    ?string $refType = null,
    ?int $refId = null,
    ?string $notes = null,
    ?string $currency = 'MXN'
): bool {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) {
        throw new \InvalidArgumentException('Amount inválido');
    }

    $currency = strtoupper(trim((string)($currency ?? 'MXN')));

    return DB::transaction(function () use ($tenantId, $amount, $externalRef, $refType, $refId, $notes, $currency) {

        // Asegurar wallet (ideal: tenant_wallets.tenant_id UNIQUE)
        $this->ensureWallet($tenantId);

        // 🔒 Lock wallet row para serializar créditos/débitos y hacer idempotencia segura
        $w = DB::table('tenant_wallets')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if (!$w) {
            // Muy raro (pero por si acaso)
            $this->ensureWallet($tenantId);
            $w = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
        }

        $walletCurrency = strtoupper((string)($w->currency ?? 'MXN'));
        if ($walletCurrency !== $currency) {
            throw new \RuntimeException("Moneda no coincide. Wallet={$walletCurrency}, request={$currency}");
        }

        // Idempotencia por external_ref (ya con lock, no hay race)
        $exists = DB::table('tenant_wallet_movements')
            ->where('tenant_id', $tenantId)
            ->where('external_ref', $externalRef)
            ->exists();

        if ($exists) return false;

        DB::table('tenant_wallets')
            ->where('tenant_id', $tenantId)
            ->update([
                'balance'       => DB::raw('balance + ' . number_format($amount, 2, '.', '')),
                'last_topup_at' => now(),
                'updated_at'    => now(),
            ]);

        DB::table('tenant_wallet_movements')->insert([
            'tenant_id'    => $tenantId,
            'type'         => 'topup',
            'amount'       => $amount,
            'currency'     => $walletCurrency,
            'ref_type'     => $refType,
            'ref_id'       => $refId,
            'external_ref' => $externalRef,
            'notes'        => $notes,
            'created_at'   => now(),
        ]);

        return true;
    });
}




}
