<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RideBroadcaster;

class ExpirePassengerRides extends Command
{
    protected $signature = 'orbana:expire-passenger-rides {--dry : No escribe, solo log}';
    protected $description = 'Expira offers vencidas y cancela rides passenger_app sin driver/ofertas vivas al vencer la ventana';

    public function handle(): int
    {
        $now = now();
        $dry = (bool) $this->option('dry');

        Log::info('orbana:expire-passenger-rides TICK', [
            'now' => $now->format('Y-m-d H:i:s'),
            'dry' => $dry,
        ]);

        // =========================================================
        // 1) Expirar ONLY offers orphaned (NO active dispatch tracks)
        // =========================================================
        DB::table('ride_offers as o')
            ->join('rides as r', function ($j) {
                $j->on('r.id', '=', 'o.ride_id')
                  ->on('r.tenant_id', '=', 'o.tenant_id');
            })
            // CRÍTICO: Excluir TODAS las ofertas que tengan track asociado
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ride_dispatch_tracks as t')
                    ->whereColumn('t.ride_id', 'r.id')
                    ->whereColumn('t.tenant_id', 'r.tenant_id')
                    ->where('t.state', '!=', 'completed');
            })
            ->where('r.requested_channel', 'passenger_app')
            ->whereNull('r.driver_id')
            ->whereNull('r.canceled_at')
            ->whereIn('r.status', ['requested', 'offered', 'searching', 'bidding'])
            ->whereIn('o.status', ['offered', 'pending_passenger'])
            ->whereNotNull('o.expires_at')
            ->where('o.expires_at', '<', $now)
            ->selectRaw('o.id as id, o.tenant_id, o.driver_id, o.ride_id')
            ->chunkById(500, function ($rows) use ($now, $dry) {
                foreach ($rows as $o) {
                    $tenantId = (int) $o->tenant_id;
                    $rideId   = (int) $o->ride_id;
                    $offerId  = (int) $o->id;

                    if ($dry) {
                        Log::info('DRY offers.expire passenger_app (orphaned)', compact('tenantId', 'rideId', 'offerId'));
                        continue;
                    }

                    $offerUpdated = DB::table('ride_offers')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $offerId)
                        ->whereIn('status', ['offered', 'pending_passenger'])
                        ->update([
                            'status'       => 'expired',
                            'response'     => DB::raw("COALESCE(response,'expired')"),
                            'responded_at' => DB::raw("COALESCE(responded_at, '{$now->format('Y-m-d H:i:s')}')"),
                            'updated_at'   => $now,
                        ]);

                    if ($offerUpdated) {
                        Log::info('offers.expired passenger_app (orphaned)', [
                            'tenant_id' => $tenantId,
                            'ride_id'   => $rideId,
                            'offer_id'  => $offerId,
                        ]);
                    }
                }
            }, 'o.id', 'id');

        // =========================================================
        // 2) Clean up tracks that should be completed but aren't
        // =========================================================
        DB::table('ride_dispatch_tracks as t')
            ->join('rides as r', function ($j) {
                $j->on('r.id', '=', 't.ride_id')
                  ->on('r.tenant_id', '=', 't.tenant_id');
            })
            ->where('r.requested_channel', 'passenger_app')
            ->whereIn('t.state', ['stand_active', 'street_active'])
            ->where(function ($query) use ($now) {
                $query->whereNotNull('r.driver_id')
                      ->orWhereNotNull('r.canceled_at')
                      ->orWhereIn('r.status', ['finished', 'canceled', 'completed']);
            })
            ->update([
                't.state' => 'completed',
                't.updated_at' => $now,
            ]);

        // =========================================================
        // 3) Normalizar conductores en estado "saltado" (MOVER a "salio")
        // =========================================================
        if (!$dry) {
            $updatedCount = DB::update("
                UPDATE taxi_stand_queue q
                INNER JOIN drivers d ON d.id = q.driver_id AND d.tenant_id = q.tenant_id
                SET q.status = 'salio',
                    q.position = 0,
                    q.joined_at = ?
                WHERE q.status = 'saltado'
                  AND d.status = 'idle'
            ", [$now]);

            if ($updatedCount > 0) {
                Log::info('taxi_stand_queue.saltado normalized to salio', [
                    'count' => $updatedCount,
                    'now' => $now->format('Y-m-d H:i:s')
                ]);
            }

            DB::table('taxi_stand_queue')
                ->where('status', 'saltado')
                ->update(['active_key' => 0]);
        } else {
            $count = DB::table('taxi_stand_queue as q')
                ->join('drivers as d', function ($j) {
                    $j->on('d.id', '=', 'q.driver_id')
                      ->on('d.tenant_id', '=', 'q.tenant_id');
                })
                ->where('q.status', 'saltado')
                ->where('d.status', 'idle')
                ->count();
            
            if ($count > 0) {
                Log::info('DRY taxi_stand_queue.saltado would be normalized to salio', [
                    'count' => $count
                ]);
            }
        }

        // =========================================================
        // 4) Cancelar RIDES passenger_app SOLO cuando:
        //    - track está en street_active
        //    - street_expires_at venció
        //    - no hay offers vivas
        // =========================================================
        DB::table('rides as r')
            ->join('ride_dispatch_tracks as t', function ($j) {
                $j->on('t.ride_id', '=', 'r.id')
                  ->on('t.tenant_id', '=', 'r.tenant_id');
            })
            ->where('r.requested_channel', 'passenger_app')
            ->whereNull('r.driver_id')
            ->whereNull('r.canceled_at')
            ->whereIn('r.status', ['requested', 'offered', 'searching', 'bidding'])
            ->where('t.state', 'street_active')
            ->whereNotNull('t.street_expires_at')
            ->where('t.street_expires_at', '<=', $now)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->whereIn('o.status', ['offered', 'pending_passenger', 'accepted']);
            })
            ->selectRaw('r.id as id, r.tenant_id, r.status as prev_status, t.street_expires_at')
            ->chunkById(200, function ($rows) use ($now, $dry) {
                foreach ($rows as $row) {
                    $tenantId = (int) $row->tenant_id;
                    $rideId   = (int) $row->id;

                    if ($dry) {
                        Log::info('DRY rides.cancel passenger_app (street window expired, no alive offers)', [
                            'tenantId' => $tenantId,
                            'rideId'   => $rideId,
                            'street_expires_at' => (string) $row->street_expires_at,
                        ]);
                        continue;
                    }

                    $this->cancelRide($tenantId, $rideId, $row->prev_status, $now, 'street_expired');
                }
            }, 'r.id', 'id');

        // =========================================================
        // 5) NUEVA SECCIÓN: Cancelar rides donde TODAS las ofertas calle hayan vencido
        // =========================================================
        DB::table('rides as r')
            ->where('r.requested_channel', 'passenger_app')
            ->whereNull('r.driver_id')
            ->whereNull('r.canceled_at')
            ->whereIn('r.status', ['requested', 'offered', 'searching', 'bidding'])
            // Solo rides que tienen al menos una oferta calle
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->where('o.kind', 'street');
            })
            // No tiene ofertas vivas (offered/pending/accepted)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->whereIn('o.status', ['offered', 'pending_passenger', 'accepted']);
            })
            // Todas las ofertas calle tienen expires_at en el pasado (vencidas)
            ->whereNotExists(function ($sub) use ($now) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->where('o.kind', 'street')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('o.expires_at')
                          ->orWhere('o.expires_at', '>', $now);
                    });
            })
            // OPCIONAL: Agregar margen de seguridad (ej: 30 segundos después de la última expiración)
            ->whereExists(function ($sub) use ($now) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id')
                    ->where('o.kind', 'street')
                    ->whereNotNull('o.expires_at')
                    ->where('o.expires_at', '<=', $now->copy()->subSeconds(30)); // Margen de 30 segundos
            })
            ->selectRaw('r.id as id, r.tenant_id, r.status as prev_status')
            ->chunkById(200, function ($rows) use ($now, $dry) {
                foreach ($rows as $row) {
                    $tenantId = (int) $row->tenant_id;
                    $rideId   = (int) $row->id;

                    if ($dry) {
                        Log::info('DRY rides.cancel passenger_app (all street offers expired)', [
                            'tenantId' => $tenantId,
                            'rideId'   => $rideId,
                        ]);
                        continue;
                    }

                    $this->cancelRide($tenantId, $rideId, $row->prev_status, $now, 'all_street_offers_expired');
                }
            }, 'r.id', 'id');

        return self::SUCCESS;
    }

    /**
     * Helper para cancelar un ride de forma consistente
     */
    private function cancelRide(int $tenantId, int $rideId, ?string $prevStatus, $now, string $reason): void
    {
        $updated = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideId)
            ->whereNull('canceled_at')
            ->whereNull('driver_id')
            ->whereNotIn('status', ['finished', 'canceled'])
            ->update([
                'status'        => 'canceled',
                'canceled_at'   => $now,
                'canceled_by'   => 'system',
                'cancel_reason' => $reason === 'street_expired' 
                    ? 'Ventana de búsqueda en calle expirada' 
                    : 'Todos los conductores declinaron',
                'updated_at'    => $now,
            ]);

        if (!$updated) return;

        DB::table('ride_status_history')->insert([
            'tenant_id'   => $tenantId,
            'ride_id'     => $rideId,
            'prev_status' => (string) ($prevStatus ?? 'offered'),
            'new_status'  => 'canceled',
            'meta'        => json_encode([
                'by' => 'system',
                'reason' => $reason,
                'cancel_reason' => $reason === 'street_expired' 
                    ? 'Ventana de búsqueda en calle expirada' 
                    : 'Todos los conductores declinaron',
            ]),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        try {
            RideBroadcaster::canceled($tenantId, $rideId, 'system', 
                $reason === 'street_expired' 
                    ? 'Ventana de búsqueda en calle expirada' 
                    : 'Todos los conductores declinaron!');
        } catch (\Throwable $e) {
            Log::warning('emit ride.canceled failed', [
                'tenant' => $tenantId,
                'ride'   => $rideId,
                'reason' => $reason,
                'msg'    => $e->getMessage(),
            ]);
        }

        Log::info('rides.canceled passenger_app', [
            'tenant_id' => $tenantId,
            'ride_id'   => $rideId,
            'reason'    => $reason,
            'prev_status' => $prevStatus,
        ]);
    }
}