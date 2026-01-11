<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantConsoleWalletController extends Controller
{
    public function credit(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'amount'       => ['required','numeric','min:1'],
            'currency'     => ['nullable','string','max:10'],
            'external_ref' => ['nullable','string','max:190'], // tu columna permite 190
            'notes'        => ['nullable','string','max:255'], // tu columna permite 255
        ]);

        $amount   = $this->money2((float)$data['amount']);
        $currency = strtoupper(trim((string)($data['currency'] ?? 'MXN')));
        $ref      = trim((string)($data['external_ref'] ?? ''));
        $notes    = trim((string)($data['notes'] ?? 'Topup manual (transferencia).'));

        $movementId = DB::transaction(function () use ($tenant, $amount, $currency, $ref, $notes) {
            $wallet = $this->walletForUpdate($tenant->id, $currency);

            // Si ya existía wallet y trae otra moneda, no mezcles.
            $walletCurrency = strtoupper((string)$wallet->currency);
            if ($walletCurrency !== $currency) {
                throw new \RuntimeException("Moneda no coincide. Wallet={$walletCurrency}, request={$currency}");
            }

            $newBalance = $this->money2(((float)$wallet->balance) + $amount);

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenant->id)
                ->update([
                    'balance'       => $newBalance,
                    'last_topup_at' => now(),
                    'updated_at'    => now(),
                ]);

            // tenant_wallet_movements: NO existe updated_at
            return DB::table('tenant_wallet_movements')->insertGetId([
                'tenant_id'    => $tenant->id,
                'type'         => 'topup',          // ✅ enum válido
                'amount'       => $amount,          // ✅ positivo
                'currency'     => $walletCurrency,
                'ref_type'     => 'transfer',
                'ref_id'       => null,
                'external_ref' => $ref ?: null,
                'notes'        => $notes ?: null,
                'created_at'   => now(),
            ]);
        });

        Log::info('SYSADMIN_WALLET_CREDIT', [
            'tenant_id'    => $tenant->id,
            'amount'       => $amount,
            'currency'     => $currency,
            'movement_id'  => $movementId,
            'external_ref' => $ref,
        ]);

        return back()->with('ok', "Saldo acreditado: +{$amount} {$currency} (mov #{$movementId}).");
    }

    public function debit(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'amount'       => ['required','numeric','min:1'],
            'external_ref' => ['nullable','string','max:190'],
            'notes'        => ['nullable','string','max:255'],
        ]);

        $amount = $this->money2((float)$data['amount']);
        $ref    = trim((string)($data['external_ref'] ?? ''));
        $notes  = trim((string)($data['notes'] ?? 'Cargo manual.'));

        $movementId = DB::transaction(function () use ($tenant, $amount, $ref, $notes) {
            // En debit, exigimos wallet existente (o si prefieres, la creas con MXN)
            $wallet = DB::table('tenant_wallets')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Este tenant aún no tiene wallet.');
            }

            $balance = (float)$wallet->balance;
            if ($balance < $amount) {
                $wc = strtoupper((string)$wallet->currency);
                throw new \RuntimeException("Saldo insuficiente. Balance={$balance} {$wc}");
            }

            $newBalance = $this->money2($balance - $amount);

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenant->id)
                ->update([
                    'balance'    => $newBalance,
                    'updated_at' => now(),
                ]);

            $currency = strtoupper((string)$wallet->currency);

            return DB::table('tenant_wallet_movements')->insertGetId([
                'tenant_id'    => $tenant->id,
                'type'         => 'debit',          // ✅ enum válido
                'amount'       => $amount,          // ✅ positivo (dirección por type)
                'currency'     => $currency,
                'ref_type'     => 'manual',
                'ref_id'       => null,
                'external_ref' => $ref ?: null,
                'notes'        => $notes ?: null,
                'created_at'   => now(),
            ]);
        });

        Log::warning('SYSADMIN_WALLET_DEBIT', [
            'tenant_id'    => $tenant->id,
            'amount'       => $amount,
            'movement_id'  => $movementId,
            'external_ref' => $ref,
        ]);

        // moneda real de wallet (para mostrarla exacta)
        $wallet = DB::table('tenant_wallets')->where('tenant_id', $tenant->id)->first();
        $currency = $wallet ? strtoupper((string)$wallet->currency) : 'MXN';

        return back()->with('ok', "Cargo aplicado: -{$amount} {$currency} (mov #{$movementId}).");
    }

    public function adjust(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'delta'        => ['required','numeric'], // puede ser + o -
            'currency'     => ['nullable','string','max:10'],
            'external_ref' => ['nullable','string','max:190'],
            'notes'        => ['required','string','max:255'],
        ]);

        $delta    = $this->money2((float)$data['delta']); // puede ser negativa
        $currency = strtoupper(trim((string)($data['currency'] ?? 'MXN')));
        $ref      = trim((string)($data['external_ref'] ?? ''));
        $notes    = trim((string)$data['notes']);

        $movementId = DB::transaction(function () use ($tenant, $delta, $currency, $ref, $notes) {
            $wallet = $this->walletForUpdate($tenant->id, $currency);

            $walletCurrency = strtoupper((string)$wallet->currency);
            if ($walletCurrency !== $currency) {
                throw new \RuntimeException("Moneda no coincide. Wallet={$walletCurrency}, request={$currency}");
            }

            $current = (float)$wallet->balance;
            $newBal  = $this->money2($current + $delta);

            if ($newBal < 0) {
                throw new \RuntimeException("El ajuste dejaría balance negativo ({$newBal}).");
            }

            DB::table('tenant_wallets')
                ->where('tenant_id', $tenant->id)
                ->update([
                    'balance'    => $newBal,
                    'updated_at' => now(),
                ]);

            return DB::table('tenant_wallet_movements')->insertGetId([
                'tenant_id'    => $tenant->id,
                'type'         => 'adjust',       // ✅ enum válido
                'amount'       => $delta,         // ✅ aquí sí va con signo
                'currency'     => $walletCurrency,
                'ref_type'     => 'adjust',
                'ref_id'       => null,
                'external_ref' => $ref ?: null,
                'notes'        => $notes,
                'created_at'   => now(),
            ]);
        });

        Log::warning('SYSADMIN_WALLET_ADJUST', [
            'tenant_id'    => $tenant->id,
            'delta'        => $delta,
            'currency'     => $currency,
            'movement_id'  => $movementId,
            'external_ref' => $ref,
        ]);

        return back()->with('ok', "Ajuste aplicado: {$delta} {$currency} (mov #{$movementId}).");
    }

    /**
     * Obtiene wallet con lockForUpdate; si no existe, la crea.
     */
    private function walletForUpdate(int $tenantId, string $currency)
    {
        $wallet = DB::table('tenant_wallets')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($wallet) return $wallet;

        DB::table('tenant_wallets')->insert([
            'tenant_id'     => $tenantId,
            'balance'       => 0.00,
            'currency'      => $currency,
            'last_topup_at' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return DB::table('tenant_wallets')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Normaliza a 2 decimales (tu schema es decimal(12,2)).
     */
    private function money2(float $v): float
    {
        return round($v, 2);
    }
}
