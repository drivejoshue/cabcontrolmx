<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerTopup;
use App\Services\PartnerWalletService;
use App\Services\PartnerTopupReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Partner\PartnerInboxService;

class PartnerManualTopupController extends Controller
{
    public function store(Request $r, Partner $partner, PartnerTopupReviewService $review)
    {
        $tenantId  = (int) $partner->tenant_id;
        $partnerId = (int) $partner->id;

        abort_if($tenantId <= 0 || $partnerId <= 0, 404);

        $data = $r->validate([
            'amount'   => ['required','numeric','min:1','max:500000'],
            'currency' => ['nullable','string','size:3'],
            'notes'    => ['nullable','string','max:500'],
            'bank_ref' => ['nullable','string','max:120'],
        ]);

        $amount   = round((float)$data['amount'], 2);
        $currency = strtoupper($data['currency'] ?? 'MXN');
        $notes    = $data['notes'] ?? null;
        $bankRef  = $data['bank_ref'] ?? null;

        $externalRef = 'PTM-' . strtoupper(bin2hex(random_bytes(6)));

        try {
            DB::transaction(function () use (
                $tenantId, $partnerId, $amount, $currency, $notes, $bankRef, $externalRef, $review
            ) {
                // 1) Crear topup manual
                $topup = PartnerTopup::create([
                    'tenant_id'  => $tenantId,
                    'partner_id' => $partnerId,
                    'provider'   => 'manual',
                    'method'     => 'manual',
                    'provider_account_slot' => null,
                    'amount'     => $amount,
                    'currency'   => $currency,
                    'bank_ref'   => $bankRef,
                    'status'     => 'pending_review',
                    'review_status' => null,
                    'review_notes'  => null,
                    'external_reference' => $externalRef,
                    'meta' => [
                        'source' => 'sysadmin_manual',
                        'created_by' => [
                            'user_id' => Auth::id(),
                            'email'   => Auth::user()->email ?? null,
                            'name'    => Auth::user()->name ?? null,
                        ],
                    ],
                ]);

                // 2) Acreditar con el flujo canónico
                $review->approveAndCredit(
                    tenantId: $tenantId,
                    topupId: (int)$topup->id,
                    reviewerId: (int)Auth::id(),
                    notes: $notes ?: 'Topup manual (SysAdmin)'
                );

                // 3) Inbox (SIEMPRE usando datos del topup)
                PartnerInboxService::notify(
                    tenantId:  (int)$topup->tenant_id,
                    partnerId: (int)$topup->partner_id,
                    type:      'topup_approved',
                    level:     'success',
                    title:     'Recarga aprobada',
                    body:      "Tu recarga manual fue aprobada y acreditada al wallet. Ref: " . ($topup->bank_ref ?: $externalRef),
                    entityType:'partner_topup',
                    entityId:  (int)$topup->id,
                    data: [
                        'amount' => (float)$topup->amount,
                        'method' => (string)$topup->method,
                        'ref'    => (string)($topup->bank_ref ?? ''),
                        'external_reference' => (string)$topup->external_reference,
                    ]
                );
            });

        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'No se pudo crear/acreditar el topup manual. Revisa logs.');
        }

        return back()->with('ok', "Topup manual acreditado. Ref: {$externalRef}");
    }
}
