<?php
namespace App\Services;

use App\Models\Ride;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateRideService
{
    public function create(array $input, int $tenantId): Ride
    {
        $tenantTz = DB::table('tenants')->where('id',$tenantId)->value('timezone')
                 ?: config('app.timezone','UTC');

        // 1) Parsear scheduled_for EN HORA LOCAL DEL TENANT
        [$scheduledLocal, $isScheduled] = $this->parseScheduledLocal($input['scheduled_for'] ?? null, $tenantTz);

        $ride = null;

        DB::transaction(function () use ($input, $tenantId, $tenantTz, $isScheduled, $scheduledLocal, &$ride) {

            // --- pasajero (igual que ya tienes) ---
            $passengerId = null;
            $snapName    = $input['passenger_name']  ?? null;
            $snapPhone   = $input['passenger_phone'] ?? null;

            if ($snapPhone) {
                $p = \App\Models\Passenger::firstOrCreate(
                    ['tenant_id'=>$tenantId,'phone'=>$snapPhone],
                    ['name'=>$snapName]
                );
                if ($snapName && $p->name !== $snapName) { $p->name = $snapName; $p->save(); }
                $passengerId = $p->id;
                if (!$snapName) $snapName = $p->name;
            }

            // AHORA local (para todos los datetimes)
            $nowLocal = Carbon::now($tenantTz);

            // --- construir Ride ---
            $ride = new Ride();
            $ride->tenant_id         = $tenantId;
            $ride->status            = $isScheduled ? 'scheduled' : 'requested';
            $ride->requested_channel = $input['requested_channel'] ?? 'dispatch';

            $ride->passenger_id    = $passengerId;
            $ride->passenger_name  = $snapName;
            $ride->passenger_phone = $snapPhone;

            $ride->origin_label = $input['origin_label'] ?? null;
            $ride->origin_lat   = isset($input['origin_lat']) ? (float)$input['origin_lat'] : null;
            $ride->origin_lng   = isset($input['origin_lng']) ? (float)$input['origin_lng'] : null;

            $ride->dest_label = $input['dest_label'] ?? null;
            $ride->dest_lat   = isset($input['dest_lat']) ? (float)$input['dest_lat'] : null;
            $ride->dest_lng   = isset($input['dest_lng']) ? (float)$input['dest_lng'] : null;

            $ride->fare_mode      = $input['fare_mode'] ?? 'meter';
            $ride->payment_method = $input['payment_method'] ?? 'cash';
            $ride->notes          = $input['notes'] ?? null;
            $ride->pax            = (int)($input['pax'] ?? 1);

            // Distancia/duración/polyline/quoted_amount (respetando modo fijo o userfixed a nivel de recálculo)
            $ride->distance_m     = $input['distance_m']     ?? null;
            $ride->duration_s     = $input['duration_s']     ?? null;
            $ride->route_polyline = $input['route_polyline'] ?? null;
            $ride->quoted_amount  = isset($input['quoted_amount']) ? (int)round($input['quoted_amount']) : null;

            // Fechas (almacenadas sin TZ, pero ya en zona del tenant)
            $ride->requested_at  = $nowLocal->format('Y-m-d H:i:s');
            $ride->scheduled_for = $scheduledLocal ? $scheduledLocal->format('Y-m-d H:i:s') : null;

            // timestamps manuales (tu modelo tiene $timestamps=false)
            $ride->created_at = $nowLocal->format('Y-m-d H:i:s');
            $ride->updated_at = $nowLocal->format('Y-m-d H:i:s');

            $ride->save();

            // ====== AUTODISPATCH CON DELAY RESPETADO ======
            if (!$isScheduled) {
                // Lee settings reales del tenant
                $cfg = \App\Services\AutoDispatchService::settings($tenantId);

                // Solo si está habilitado
                if ($cfg->enabled) {
                    if ((int)$cfg->delay_s <= 0) {
                        // Delay 0 => ola inmediata
                        try {
                            \App\Services\AutoDispatchService::kickoff(
                                tenantId: $tenantId,
                                rideId:   $ride->id,
                                lat:      (float)$ride->origin_lat,
                                lng:      (float)$ride->origin_lng,
                                km:       (float)$cfg->radius_km,
                                expires:  (int)$cfg->expires_s,
                                limitN:   (int)$cfg->limit_n,
                                autoAssignIfSingle: (bool)$cfg->auto_assign_if_single
                            );
                        } catch (\Throwable $e) {
                            \Log::warning('autodispatch immediate failed: '.$e->getMessage());
                        }
                    } else {
                        // Delay > 0 => NO dispares aquí.
                        // Se disparará por /api/dispatch/tick (front con setTimeout o un job/cron).
                        \Log::info('autodispatch queued (frontend/cron will tick)', [
                            'ride_id' => $ride->id,
                            'delay_s' => $cfg->delay_s
                        ]);
                    }
                }
            }
            // ==============================================
        });

        return $ride;
    }

    // datetime-local o ISO; devuelve [Carbon en TZ tenant, bool]
    private function parseScheduledLocal(?string $raw, string $tenantTz): array
    {
        if (empty($raw)) return [null,false];
        $raw = trim($raw);
        try {
            $hasTz = (bool)preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $raw);
            if ($hasTz) {
                $c = Carbon::parse($raw)->setTimezone($tenantTz);
            } else {
                $fmt = strlen($raw) === 16 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s';
                $c = Carbon::createFromFormat($fmt, $raw, $tenantTz);
            }
            return [$c,true];
        } catch (\Throwable $e) {
            \Log::warning('parseScheduledLocal fail', ['raw'=>$raw,'tz'=>$tenantTz,'msg'=>$e->getMessage()]);
            return [null,false];
        }
    }
}
