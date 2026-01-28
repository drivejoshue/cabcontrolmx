<?php

namespace App\Services;

use App\Models\PartnerTopup;
use App\Models\PartnerWallet;
use Illuminate\Support\Facades\DB;

class PartnerWalletService
{
    public static function ensureWallet(int $tenantId, int $partnerId, string $currency = 'MXN'): PartnerWallet
    {
        return PartnerWallet::firstOrCreate(
            ['tenant_id' => $tenantId, 'partner_id' => $partnerId],
            ['balance' => 0, 'currency' => $currency]
        );
    }

    /**
     * Debita del wallet del partner y crea movimiento.
     * - Usa lockForUpdate para balance consistente.
     * - Lanza excepción si no alcanza.
     * - external_ref recomendado para idempotencia (ya tienes UNIQUE por partner).
     */
    public static function debit(
        int $tenantId,
        int $partnerId,
        string $amount,
        string $currency = 'MXN',
        string $type = 'billing',
        ?string $refType = null,
        ?int $refId = null,
        ?string $externalRef = null,
        ?string $notes = null,
        array $meta = [],
        ?int $actorUserId = null
    ): int {
        $amount = (string)number_format((float)$amount, 2, '.', '');
        if (bccomp($amount, '0.00', 2) <= 0) {
            return 0;
        }

        // Normalizar externalRef: no guardar '' vacío (para que UNIQUE sea útil)
        if (is_string($externalRef)) {
            $externalRef = trim($externalRef);
            if ($externalRef === '') $externalRef = null;
        }

        return DB::transaction(function () use (
            $tenantId, $partnerId, $amount, $currency, $type, $refType, $refId, $externalRef, $notes, $meta, $actorUserId
        ) {
            $wallet = PartnerWallet::where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = self::ensureWallet($tenantId, $partnerId, $currency);
                $wallet = PartnerWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            // Idempotencia extra por external_ref si viene
            if ($externalRef) {
                $exists = DB::table('partner_wallet_movements')
                    ->where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->where('external_ref', $externalRef)
                    ->exists();

                if ($exists) {
                    return 0;
                }
            }

            $current = (string)number_format((float)$wallet->balance, 2, '.', '');
            $newBalance = bcsub($current, $amount, 2);

            if (bccomp($newBalance, '0.00', 2) < 0) {
                throw new \RuntimeException('Saldo insuficiente en wallet del partner para esta operación.');
            }

            $movementId = DB::table('partner_wallet_movements')->insertGetId([
                'tenant_id'     => $tenantId,
                'partner_id'    => $partnerId,
                'type'          => $type,
                'direction'     => 'debit',
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'currency'      => $currency,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'external_ref'  => $externalRef,
                'notes'         => $notes,
                'meta'          => !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at'    => now(),
            ]);

            $wallet->balance = $newBalance;
            $wallet->currency = $currency;
            $wallet->save();

            return (int)$movementId;
        });
    }

    /**
     * Acredita un topup:
     * - suma al PartnerWallet (sub-ledger)
     * - suma al TenantWallet (caja real)
     * - crea movimientos partner_wallet_movements (+ idealmente tenant_wallet_movements)
     * - idempotente: si ya está credited, no repite
     */
public static function applyTopup(PartnerTopup $topup, ?int $creditedByUserId = null): void
{
    DB::transaction(function () use ($topup, $creditedByUserId) {

        // 0) Lock del topup (evita doble approve simultáneo)
        /** @var PartnerTopup|null $t */
        $t = PartnerTopup::whereKey($topup->id)->lockForUpdate()->first();
        if (!$t) return;

        // Idempotencia fuerte
        if ($t->credited_at || $t->status === 'credited') {
            return;
        }

        // Si quieres estrictamente solo pending_review:
        if ($t->status !== 'pending_review' && $t->status !== 'approved') {
            // evita acreditar topups en estados raros
            return;
        }

        // Lock wallet partner
        $wallet = PartnerWallet::where('tenant_id', $t->tenant_id)
            ->where('partner_id', $t->partner_id)
            ->lockForUpdate()
            ->first();

        $currency = strtoupper((string)($t->currency ?? 'MXN'));

        if (!$wallet) {
            $wallet = self::ensureWallet((int)$t->tenant_id, (int)$t->partner_id, $currency);
            $wallet = PartnerWallet::where('id', $wallet->id)->lockForUpdate()->first();
        }

        // Moneda consistente
        $walletCurrency = strtoupper((string)($wallet->currency ?? 'MXN'));
        if ($walletCurrency !== $currency) {
            throw new \RuntimeException("Moneda no coincide. Wallet={$walletCurrency}, topup={$currency}");
        }

        // External ref canónico (SIEMPRE no-null)
        $ext = $t->mp_payment_id ?: ($t->external_reference ?: null);
        $ext = is_string($ext) ? trim($ext) : null;
        if (!$ext) $ext = 'partner_topup:' . (int)$t->id;

        // Idempotencia por external_ref (sub-ledger partner)
        $already = DB::table('partner_wallet_movements')
            ->where('tenant_id', (int)$t->tenant_id)
            ->where('partner_id', (int)$t->partner_id)
            ->where('external_ref', $ext)
            ->exists();

        if ($already) {
    $pwmId = (int) DB::table('partner_wallet_movements')
        ->where('tenant_id', (int)$t->tenant_id)
        ->where('partner_id', (int)$t->partner_id)
        ->where('external_ref', $ext)
        ->value('id');

            if ($t->status !== 'credited') {
                $t->status = 'credited';
                $t->credited_at = $t->credited_at ?? now();
            }
            if (empty($t->apply_wallet_movement_id) && $pwmId > 0) {
                $t->apply_wallet_movement_id = $pwmId;
            }
            $t->save();
            return;
        }


        $amount = (float)$t->amount;
        if ($amount <= 0) {
            throw new \RuntimeException('Monto inválido para topup.');
        }

        // 1) Creditar TenantWallet (caja real / escrow)
        /** @var TenantWalletService $tw */
        $tw = app(TenantWalletService::class);

        // IMPORTANTE: creditTopupWithRef debe ser idempotente por externalRef
        $tw->creditTopupWithRef(
            tenantId: (int)$t->tenant_id,
            amount: $amount,
            externalRef: $ext,
            refType: 'partner_topups',
            refId: (int)$t->id,
            notes: 'Partner topup acreditado (escrow tenant wallet)',
            currency: $currency
        );

        // 2) Movement partner wallet (sub-ledger / reservado del partner)
        $current = (string)number_format((float)$wallet->balance, 2, '.', '');
        $add     = (string)number_format($amount, 2, '.', '');
        $newBal  = bcadd($current, $add, 2);

        $pwmId = DB::table('partner_wallet_movements')->insertGetId([
            'tenant_id'     => (int)$t->tenant_id,
            'partner_id'    => (int)$t->partner_id,
            'type'          => 'topup',
            'direction'     => 'credit',
            'amount'        => $add,
            'balance_after' => $newBal,
            'currency'      => $currency,
            'ref_type'      => 'partner_topups',
            'ref_id'        => (int)$t->id,
            'external_ref'  => $ext,
            'notes'         => 'Topup acreditado',
            'meta'          => $creditedByUserId ? json_encode(['credited_by' => $creditedByUserId], JSON_UNESCAPED_UNICODE) : null,
            'created_at'    => now(),
        ]);

        $wallet->balance = $newBal;
        $wallet->currency = $currency;
        $wallet->last_topup_at = now();
        $wallet->save();

        // 3) Marcar topup
        $t->status = 'credited';
        $t->credited_at = now();
        $t->apply_wallet_movement_id = $pwmId;
        $t->save();
    });
}


    private static function creditTenantWallet(
        int $tenantId,
        string $amount,
        string $currency,
        string $refType,
        int $refId,
        string $externalRef,
        string $notes,
        array $meta = []
    ): void {
        // Si tu TenantWalletService existe, úsalo:
        if (class_exists(\App\Services\TenantWalletService::class) && method_exists(\App\Services\TenantWalletService::class, 'creditTopup')) {
            \App\Services\TenantWalletService::creditTopup(
                tenantId: $tenantId,
                amount: (float)$amount,
                currency: $currency,
                externalRef: $externalRef ?: null,
                notes: $notes
            );
            return;
        }

        // Fallback directo a DB (tenant_wallets + tenant_wallet_movements)
        DB::transaction(function () use ($tenantId, $amount, $currency, $refType, $refId, $externalRef, $notes, $meta) {

            $wallet = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$wallet) {
                DB::table('tenant_wallets')->insert([
                    'tenant_id' => $tenantId,
                    'balance' => 0,
                    'currency' => $currency,
                    'last_topup_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $wallet = DB::table('tenant_wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            }

            $newBalance = bcadd((string)$wallet->balance, (string)$amount, 2);

            DB::table('tenant_wallet_movements')->insert([
                'tenant_id' => $tenantId,
                'type' => 'topup',
                'amount' => $amount,
                'currency' => $currency,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'external_ref' => $externalRef ?: null,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            DB::table('tenant_wallets')->where('tenant_id', $tenantId)->update([
                'balance' => $newBalance,
                'currency' => $currency,
                'last_topup_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }


    
}
