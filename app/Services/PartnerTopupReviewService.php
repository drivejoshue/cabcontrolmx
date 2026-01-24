<?php


namespace App\Services;

use App\Models\PartnerTopup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerTopupReviewService
{
    public function approveAndCredit(int $tenantId, int $topupId, int $reviewerId, ?string $notes = null): array
    {
        return DB::transaction(function () use ($tenantId, $topupId, $reviewerId, $notes) {

            /** @var PartnerTopup $t */
            $t = PartnerTopup::where('tenant_id', $tenantId)
                ->where('id', $topupId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotencia
            if (($t->status ?? '') === 'credited' || !empty($t->credited_at) || !empty($t->apply_wallet_movement_id)) {
                return ['ok' => true, 'already' => true, 'status' => $t->status];
            }

            if (($t->status ?? '') === 'rejected') {
                throw ValidationException::withMessages([
                    'topup' => 'Este topup ya fue rechazado y no puede acreditarse.',
                ]);
            }

            $amount = (float) $t->amount;
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Monto inválido para acreditar.',
                ]);
            }

            $currency = $t->currency ?: 'MXN';
            $ext = $t->external_reference ?: ('PT-'.$t->id);

            // 1) Creditar wallet del partner (subwallet)
            // Ajusta si tu PartnerWalletService::credit devuelve ID/modelo.
            $partnerMov = \App\Services\PartnerWalletService::credit(
                tenantId: (int)$tenantId,
                partnerId: (int)$t->partner_id,
                amount: number_format($amount, 2, '.', ''),
                currency: $currency,
                type: 'topup',
                refType: 'partner_topups',
                refId: (int)$t->id,
                externalRef: 'partner_topup:' . $ext,
                notes: 'Recarga aprobada por SysAdmin',
                meta: [
                    'provider' => $t->provider,
                    'method' => $t->method,
                    'bank_ref' => $t->bank_ref,
                ]
            );

            $partnerMovId = is_object($partnerMov) ? (int)($partnerMov->id ?? 0) : (int)$partnerMov;

            // 2) Creditar tenant wallet (wallet madre) - para que exista settlement real
            /** @var \App\Services\TenantWalletService $tw */
            $tw = app(\App\Services\TenantWalletService::class);
            $tw->ensureWallet($tenantId);

            // Ideal: método específico para auditar origen "partner_topup"
            $tenantMovId = $tw->creditTopup(
                tenantId: $tenantId,
                amount: (float)$amount,
                refType: 'partner_topups',
                refId: (int)$t->id,
                externalRef: 'tenant_credit_from_partner_topup:' . $ext,
                notes: 'Ingreso por recarga de partner #' . (int)$t->partner_id,
                currency: $currency
            );

            $meta = is_array($t->meta) ? $t->meta : (array)($t->meta ? json_decode((string)$t->meta, true) : []);
            $meta['tenant_wallet_movement_id'] = $tenantMovId;
            $meta['partner_wallet_movement_id'] = $partnerMovId;

            // Update topup
            $t->status = 'credited';
            $t->review_status = 'approved';
            $t->review_notes = $notes;
            $t->reviewed_by = $reviewerId;
            $t->reviewed_at = now();
            $t->credited_at = now();
            $t->paid_at = $t->paid_at ?: now();
            $t->apply_wallet_movement_id = $partnerMovId ?: null;
            $t->meta = $meta;
            $t->save();

            return ['ok' => true, 'credited' => true, 'tenant_mov' => $tenantMovId, 'partner_mov' => $partnerMovId];
        });
    }

    public function reject(int $tenantId, int $topupId, int $reviewerId, string $notes): array
    {
        return DB::transaction(function () use ($tenantId, $topupId, $reviewerId, $notes) {

            $t = PartnerTopup::where('tenant_id', $tenantId)
                ->where('id', $topupId)
                ->lockForUpdate()
                ->firstOrFail();

            if (($t->status ?? '') === 'credited') {
                throw ValidationException::withMessages([
                    'topup' => 'Este topup ya fue acreditado; no puede rechazarse.',
                ]);
            }

            if (($t->status ?? '') === 'rejected') {
                return ['ok' => true, 'already' => true, 'status' => 'rejected'];
            }

            $t->status = 'rejected';
            $t->review_status = 'rejected';
            $t->review_notes = $notes;
            $t->reviewed_by = $reviewerId;
            $t->reviewed_at = now();
            $t->save();

            return ['ok' => true, 'rejected' => true];
        });
    }
}
