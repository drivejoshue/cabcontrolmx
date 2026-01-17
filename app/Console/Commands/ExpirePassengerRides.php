<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RideBroadcaster;


class ExpirePassengerRides extends Command
{
    protected $signature = 'orbana:expire-passenger-rides {--dry : No escribe, solo log}';
    protected $description = 'Expira ride_offers vencidas y cancela rides passenger_app sin conductores/ofertas';

    public function handle(): int
    {
        $now = now();
        $dry = (bool)$this->option('dry');

        Log::info('orbana:expire-passenger-rides TICK', [
            'now' => $now->format('Y-m-d H:i:s'),
            'dry' => $dry,
        ]);

        // =========================================================
        // 1) Expirar OFFERS vencidas (offered -> expired)
        //    y cancelar el RIDE (si aún sigue vivo)
        // =========================================================
        DB::table('ride_offers')
            ->select('id','tenant_id','driver_id','ride_id')
           ->whereIn('status', ['offered', 'pending_passenger'])

            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now, $dry) {
                foreach ($rows as $o) {
                    $tenantId = (int)$o->tenant_id;
                    $rideId   = (int)$o->ride_id;
                    $offerId  = (int)$o->id;

                    if ($dry) {
                        Log::info('DRY offers.expire', compact('tenantId','rideId','offerId'));
                        continue;
                    }

                    // 1.1) Expira la offer (anti-race)
                   $offerUpdated = DB::table('ride_offers')
                      ->where('id', $offerId)
                      ->whereIn('status', ['offered','pending_passenger'])
                      ->update([
                          'status'       => 'expired',
                          'response'     => DB::raw("COALESCE(response,'expired')"),
                          'responded_at' => DB::raw("COALESCE(responded_at,'{$now->format('Y-m-d H:i:s')}')"),
                          'updated_at'   => $now,
                      ]);


                    if (!$offerUpdated) {
                        continue;
                    }

                    

                    // 1.2) Cancela el ride (solo si sigue activo)
                    $rideUpdated = DB::table('rides')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $rideId)
                        ->whereNull('canceled_at')
                        ->whereNotIn('status', ['finished','canceled'])
                        ->update([
                            'status'        => 'canceled',
                            'canceled_at'   => $now,
                            'canceled_by'   => 'system',
                            'cancel_reason' => 'Oferta expirada',
                            'updated_at'    => $now,
                        ]);

                    if ($rideUpdated) {
                        DB::table('ride_status_history')->insert([
                            'tenant_id'   => $tenantId,
                            'ride_id'     => $rideId,
                            'prev_status' => 'offered',
                            'new_status'  => 'canceled',
                            'meta'        => json_encode([
                                'by' => 'system',
                                'reason' => 'Oferta expirada',
                                'offer_id' => $offerId,
                            ]),
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ]);

                        try {
                            RideBroadcaster::canceled($tenantId, $rideId, 'system', 'La oferta ha expirado, no se eocntrarn conductores');
                        } catch (\Throwable $e) {
                            Log::warning('emit ride.canceled failed (offer expired)', [
                                'tenant' => $tenantId,
                                'ride'   => $rideId,
                                'offer'  => $offerId,
                                'msg'    => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

        // =========================================================
        // 2) Cancelar RIDES offered sin offers (no hubo kick)
        //    cuando requested_at + offer_expires_sec <= now
        // =========================================================

        $q = DB::table('rides as r')
            ->join('dispatch_settings as ds', 'ds.tenant_id', '=', 'r.tenant_id')
            ->where('r.status', 'offered')
            ->where('r.requested_channel', 'passenger_app')
            ->whereNotNull('r.requested_at')
            ->whereNull('r.canceled_at')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ride_offers as o')
                    ->whereColumn('o.tenant_id', 'r.tenant_id')
                    ->whereColumn('o.ride_id', 'r.id');
            })
            ->whereRaw(
                "DATE_ADD(r.requested_at, INTERVAL ds.offer_expires_sec SECOND) <= ?",
                [$now->format('Y-m-d H:i:s')]
            )
            ->select([
                'r.id as ride_id',
                'r.tenant_id',
                'r.requested_at',
                'ds.offer_expires_sec'
            ])
            ->orderBy('r.id');

        $q->chunkById(200, function ($rows) use ($now, $dry) {
            foreach ($rows as $row) {
                $tenantId = (int)$row->tenant_id;
                $rideId   = (int)$row->ride_id;

                if ($dry) {
                    Log::info('DRY rides.expire_no_offers', [
                        'tenantId' => $tenantId,
                        'rideId' => $rideId,
                        'offer_expires_sec' => (int)$row->offer_expires_sec,
                    ]);
                    continue;
                }

                // UPDATE directo a rides (sin JOIN / sin alias r.)
                $updated = DB::table('rides')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $rideId)
                    ->where('status', 'offered')
                    ->whereNull('canceled_at')
                    ->update([
                        'status'        => 'canceled',
                        'canceled_at'   => $now,
                        'canceled_by'   => 'system',
                        'cancel_reason' => 'Sin conductores disponibles',
                        'updated_at'    => $now,
                    ]);

                if (!$updated) {
                    continue;
                }

                DB::table('ride_status_history')->insert([
                    'tenant_id'   => $tenantId,
                    'ride_id'     => $rideId,
                    'prev_status' => 'offered',
                    'new_status'  => 'canceled',
                    'meta'        => json_encode([
                        'by' => 'system',
                        'reason' => 'Sin conductores disponibles',
                        'offer_expires_sec' => (int)$row->offer_expires_sec,
                    ]),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);

                try {
                     // ✅ Evento al pasajero
                    RideBroadcaster::canceled($tenantId, $rideId, 'system', 'Sin conductores disponibles');
               
                } catch (\Throwable $e) {
                    Log::warning('emit ride.canceled failed (no offers)', [
                        'tenant' => $tenantId,
                        'ride'   => $rideId,
                        'msg'    => $e->getMessage(),
                    ]);
                }
            }
        }, 'r.id'); // CLAVE: paginar por r.id real

        return self::SUCCESS;
    }
}
