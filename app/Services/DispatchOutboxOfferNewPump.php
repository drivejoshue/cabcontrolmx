<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\OfferBroadcaster;


class DispatchOutboxOfferNewPump
{
    /**
     * Procesa SOLO topic = 'offer.new'
     * - lockea por fila
     * - emite OfferBroadcaster::emitNew(offer_id)
     * - marca SENT o FAILED con retry simple
     */
    public static function pump(?int $tenantId = null, int $limit = 200): int
{
    $processed = 0;

    $q = DB::table('dispatch_outbox')
        ->whereIn('status', ['PENDING', 'FAILED'])
        ->where('topic', 'offer.new')
        ->where(function ($w) {
            $w->whereNull('available_at')->orWhere('available_at', '<=', now());
        })
        ->where(function ($w) {
            $w->whereNull('locked_at')->orWhere('locked_at', '<', DB::raw("DATE_SUB(NOW(), INTERVAL 10 SECOND)"));
        })
        ->orderBy('id', 'asc')
        ->limit($limit);

    if ($tenantId) $q->where('tenant_id', $tenantId);

    $items = $q->get(['id','tenant_id','offer_id','attempts']);

    foreach ($items as $it) {
        $locked = DB::table('dispatch_outbox')
            ->where('id', $it->id)
            ->whereIn('status', ['PENDING', 'FAILED'])
            ->where('topic', 'offer.new')
            ->where(function ($w) {
                $w->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->where(function ($w) {
                $w->whereNull('locked_at')->orWhere('locked_at', '<', DB::raw("DATE_SUB(NOW(), INTERVAL 10 SECOND)"));
            })
            ->update([
                'status'     => 'PROCESSING',
                'locked_at'  => now(),
                'locked_by'  => 'outbox.offernew',
                'updated_at' => now(),
            ]);

        if ($locked !== 1) continue;

        try {
            $offer = DB::table('ride_offers')
                ->where('id', (int)$it->offer_id)
                ->where('tenant_id', (int)$it->tenant_id)
                ->first(['id','tenant_id','driver_id','ride_id','status','expires_at']);

            $expiresAt = $offer && $offer->expires_at ? Carbon::parse($offer->expires_at) : null;

            $isAlive = $offer
                && in_array((string)$offer->status, ['offered','pending_passenger'], true)
                && $expiresAt
                && $expiresAt->isFuture();

            if (!$isAlive) {
                DB::table('dispatch_outbox')
                    ->where('id', $it->id)
                    ->update([
                        'status'     => 'SENT', // descartado
                        'locked_at'  => null,
                        'locked_by'  => null,
                        'last_error' => 'stale_or_expired_offer',
                        'updated_at' => now(),
                    ]);
                continue;
            }

            // Outbox es la autoridad: si está PROCESSING aquí, emitimos.
            OfferBroadcaster::emitNew((int)$it->offer_id);

            DB::table('dispatch_outbox')
                ->where('id', $it->id)
                ->update([
                    'status'     => 'SENT',
                    'locked_at'  => null,
                    'locked_by'  => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

            $processed++;

        } catch (\Throwable $e) {
            $attempts = (int)($it->attempts ?? 0) + 1;
            $delay = min(60, 2 ** min($attempts, 5));

            DB::table('dispatch_outbox')
                ->where('id', $it->id)
                ->update([
                    'status'       => $attempts >= 20 ? 'DEAD' : 'FAILED',
                    'attempts'     => $attempts,
                    'available_at' => now()->addSeconds($delay),
                    'locked_at'    => null,
                    'locked_by'    => null,
                    'last_error'   => mb_substr($e->getMessage(), 0, 1000),
                    'updated_at'   => now(),
                ]);

            Log::error('Outbox offer.new failed', [
                'outbox_id' => $it->id,
                'offer_id'  => $it->offer_id,
                'attempts'  => $attempts,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    return $processed;
}


   
}
