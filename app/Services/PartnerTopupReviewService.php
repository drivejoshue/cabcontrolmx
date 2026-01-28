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

            // Idempotencia fuerte
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

            // ✅ Marcar revisión (approved) ANTES de aplicar, para trazabilidad
            $t->status        = 'approved';
            $t->review_status = 'approved';
            $t->review_notes  = $notes;
            $t->reviewed_by   = $reviewerId;
            $t->reviewed_at   = now();
            $t->save();

            // ✅ Aplicar dinero (tenant wallet + partner wallet) y marcar credited
            // applyTopup ya maneja lock/idempotencia por external_ref y también marca credited.
            \App\Services\PartnerWalletService::applyTopup($t, $reviewerId);

            // Refrescar
            $t->refresh();

            return [
                'ok' => true,
                'credited' => (($t->status ?? '') === 'credited'),
                'partner_mov' => (int)($t->apply_wallet_movement_id ?? 0),
                'status' => $t->status,
            ];
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

            $t->status        = 'rejected';
            $t->review_status = 'rejected';
            $t->review_notes  = $notes;
            $t->reviewed_by   = $reviewerId;
            $t->reviewed_at   = now();
            $t->save();

            return ['ok' => true, 'rejected' => true];
        });
    }



}
