<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Services\Geo\GoogleMapsService;
use Illuminate\Support\Facades\Cache;

class PartnerMonitorApiController extends Controller
{
    /**
     * ✅ Endpoint CANÓNICO: snapshot completo (drivers + stands + active_rides)
     */
    public function snapshot(Request $request)
    {
        [$tenantId, $partnerId] = $this->ctx($request);

        $drivers = $this->queryDrivers($tenantId, $partnerId);
        $rides   = $this->queryActiveRides($tenantId, $partnerId);
        $stands  = $this->queryTaxiStands($tenantId, $partnerId);

        return response()->json([
            'ok'           => true,
            'server_time'  => now()->toDateTimeString(),
            'partner_id'   => $partnerId,
            'drivers'      => $drivers,
            'stands'       => $stands,
            'active_rides' => $rides,

            // compat con JS previo
            'items'        => $rides,
        ]);
    }

    /**
     * ♻️ Alias legacy (NO lógica propia)
     */
    public function bootstrap(Request $request)
    {
        return $this->snapshot($request);
    }

    /**
     * ♻️ Alias legacy (NO lógica propia)
     */
    public function activeRides(Request $request)
    {
        return $this->snapshot($request);
    }

    private function ctx(Request $request): array
    {
        $user = $request->user();

        $tenantId  = (int)($user->tenant_id ?? 0);
        $partnerId = (int) session('partner_id');

        if (!$tenantId || !$partnerId) {
            abort(403, 'partner_ctx_missing');
        }

        return [$tenantId, $partnerId];
    }

    /**
     * Drivers + última ubicación + ride_status + turno + vehículo + (cola opcional)
     * Incluye URLs de foto (driver/vehículo) listas para el front.
     */
    private function queryDrivers(int $tenantId, int $partnerId): array
    {
        if (!Schema::hasTable('drivers') || !Schema::hasTable('driver_locations')) return [];

        // 1) última ubicación por driver (tenant)
        $latestPerDriver = DB::table('driver_locations as dl1')
            ->select('dl1.tenant_id', 'dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
            ->where('dl1.tenant_id', $tenantId)
            ->groupBy('dl1.tenant_id', 'dl1.driver_id');

        $locs = DB::table('driver_locations as dl')
            ->joinSub($latestPerDriver, 'last', function ($j) {
                $j->on('dl.tenant_id', '=', 'last.tenant_id')
                    ->on('dl.driver_id', '=', 'last.driver_id')
                    ->on('dl.id', '=', 'last.last_id');
            })
            ->select(
                'dl.tenant_id',
                'dl.driver_id',
                'dl.lat',
                'dl.lng',
                'dl.reported_at',
                DB::raw('COALESCE(dl.heading_deg, dl.bearing) as bearing'),
                DB::raw('CASE WHEN dl.reported_at >= (NOW() - INTERVAL 90 SECOND) THEN 1 ELSE 0 END AS is_fresh')
            );

        // 2) ride_status por driver (prioridad)
        $assignedOrInProgress = DB::table('rides as r')
            ->select([
                'r.driver_id',
                DB::raw("
                    CASE
                      WHEN UPPER(r.status) IN ('ON_BOARD','ONBOARD','BOARDING') THEN 'on_board'
                      WHEN UPPER(r.status) = 'EN_ROUTE'                         THEN 'en_route'
                      WHEN UPPER(r.status) = 'ARRIVED'                          THEN 'arrived'
                      WHEN UPPER(r.status) IN ('ACCEPTED','ASSIGNED')           THEN 'accepted'
                      WHEN UPPER(r.status) = 'REQUESTED'                        THEN 'requested'
                      WHEN UPPER(r.status) = 'SCHEDULED'                        THEN 'scheduled'
                      ELSE LOWER(r.status)
                    END AS ride_status
                ")
            ])
            ->where('r.tenant_id', $tenantId)
            ->whereNotNull('r.driver_id')
            ->whereIn(DB::raw('UPPER(r.status)'), [
                'ON_BOARD','ONBOARD','BOARDING','EN_ROUTE','ARRIVED','ACCEPTED','ASSIGNED','REQUESTED','SCHEDULED'
            ]);

        $liveOffers = DB::table('ride_offers as o')
            ->join('rides as r', function ($j) use ($tenantId) {
                $j->on('r.id', '=', 'o.ride_id')
                    ->where('r.tenant_id', '=', $tenantId);
            })
            ->select([
                'o.driver_id',
                DB::raw("'offered' as ride_status")
            ])
            ->where('o.tenant_id', $tenantId)
            ->whereRaw('o.expires_at IS NULL OR o.expires_at > NOW()')
            ->where(DB::raw('LOWER(o.status)'), 'offered');

        $activeUnion = $assignedOrInProgress->unionAll($liveOffers);

        $activeWithPrio = DB::query()
            ->fromSub($activeUnion, 'ar0')
            ->select([
                'ar0.driver_id',
                DB::raw("
                    CASE ar0.ride_status
                        WHEN 'on_board'  THEN 1
                        WHEN 'en_route'  THEN 2
                        WHEN 'arrived'   THEN 3
                        WHEN 'accepted'  THEN 4
                        WHEN 'offered'   THEN 5
                        WHEN 'requested' THEN 6
                        WHEN 'scheduled' THEN 7
                        ELSE 99
                    END AS prio
                "),
            ]);

        $minPrioPerDriver = DB::query()
            ->fromSub($activeWithPrio, 'ap')
            ->select('ap.driver_id', DB::raw('MIN(ap.prio) as min_prio'))
            ->groupBy('ap.driver_id');

        $activeForDriver = DB::query()
            ->fromSub($minPrioPerDriver, 'mp')
            ->select([
                'mp.driver_id',
                DB::raw("
                    CASE mp.min_prio
                        WHEN 1 THEN 'on_board'
                        WHEN 2 THEN 'en_route'
                        WHEN 3 THEN 'arrived'
                        WHEN 4 THEN 'accepted'
                        WHEN 5 THEN 'offered'
                        WHEN 6 THEN 'requested'
                        WHEN 7 THEN 'scheduled'
                        ELSE NULL
                    END AS ride_status
                "),
            ]);

        // 2.5) cola (taxi_stand_queue) opcional: stand_id + queue_pos por driver
        $queueSub = null;
        if (Schema::hasTable('taxi_stand_queue')) {
            $qPosCol = Schema::hasColumn('taxi_stand_queue', 'queue_pos') ? 'queue_pos'
                : (Schema::hasColumn('taxi_stand_queue', 'queue_position') ? 'queue_position'
                    : (Schema::hasColumn('taxi_stand_queue', 'position') ? 'position' : null));

            $latestQ = DB::table('taxi_stand_queue as q1')
                ->select('q1.tenant_id', 'q1.driver_id', DB::raw('MAX(q1.id) as last_id'))
                ->where('q1.tenant_id', $tenantId)
                ->groupBy('q1.tenant_id', 'q1.driver_id');

            $qs = DB::table('taxi_stand_queue as q')
                ->joinSub($latestQ, 'lastq', function ($j) {
                    $j->on('q.tenant_id', '=', 'lastq.tenant_id')
                        ->on('q.driver_id', '=', 'lastq.driver_id')
                        ->on('q.id', '=', 'lastq.last_id');
                })
                ->select([
                    'q.tenant_id',
                    'q.driver_id',
                    Schema::hasColumn('taxi_stand_queue', 'stand_id') ? 'q.stand_id' : DB::raw('NULL as stand_id'),
                    $qPosCol ? DB::raw("q.$qPosCol as queue_pos") : DB::raw('NULL as queue_pos'),
                    Schema::hasColumn('taxi_stand_queue', 'status') ? DB::raw('LOWER(q.status) as queue_status') : DB::raw('NULL as queue_status'),
                ])
                ->where('q.tenant_id', $tenantId);

            if (Schema::hasColumn('taxi_stand_queue', 'left_at')) {
                $qs->whereNull('q.left_at');
            }
            if (Schema::hasColumn('taxi_stand_queue', 'status')) {
                $qs->whereIn(DB::raw('LOWER(q.status)'), ['en_cola', 'asignado', 'saltado']);
            }

            $queueSub = $qs;
        }

        // 3) main query drivers + shift + vehicle + loc + ride_status + (cola)
        $q = DB::table('drivers')
            ->where('drivers.tenant_id', $tenantId)
            ->whereRaw('LOWER(COALESCE(drivers.status,"offline")) != "offline"')

            ->leftJoin('driver_shifts as ds', function ($j) use ($tenantId) {
                $j->on('ds.driver_id', '=', 'drivers.id')
                    ->where('ds.tenant_id', '=', $tenantId)
                    ->whereNull('ds.ended_at');
            })

            ->leftJoinSub($locs, 'loc', function ($j) use ($tenantId) {
                $j->on('loc.driver_id', '=', 'drivers.id')
                    ->where('loc.tenant_id', '=', $tenantId);
            })

            ->leftJoinSub($activeForDriver, 'ar', function ($j) {
                $j->on('ar.driver_id', '=', 'drivers.id');
            })

            ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
                $j->on('v.id', '=', 'ds.vehicle_id')
                    ->where('v.tenant_id', '=', $tenantId);
            });

        if ($queueSub) {
            $q->leftJoinSub($queueSub, 'tq', function ($j) use ($tenantId) {
                $j->on('tq.driver_id', '=', 'drivers.id')
                    ->where('tq.tenant_id', '=', $tenantId);
            });
        }

        // ✅ filtro partner sin asumir una sola columna
        if (Schema::hasColumn('drivers', 'partner_id')) {
            $q->where('drivers.partner_id', $partnerId);
        } elseif (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'partner_id')) {
            $q->where('v.partner_id', $partnerId);
        } elseif (Schema::hasTable('driver_shifts') && Schema::hasColumn('driver_shifts', 'partner_id')) {
            $q->where('ds.partner_id', $partnerId);
        }

        $rows = $q->select([
                DB::raw('drivers.id as driver_id'),
                'drivers.name',
                DB::raw('drivers.foto_path as driver_foto_path'),

                DB::raw('loc.lat as lat'),
                DB::raw('loc.lng as lng'),
                DB::raw('loc.bearing as bearing'),
                DB::raw('loc.reported_at as updated_at'),
                DB::raw('loc.is_fresh as is_fresh'),

                DB::raw('LOWER(COALESCE(drivers.status,"offline")) as driver_status'),
                DB::raw('ar.ride_status as ride_status'),
                DB::raw('CASE WHEN ds.id IS NULL THEN 0 ELSE 1 END AS shift_open'),

                DB::raw('COALESCE(v.type,"sedan") as vehicle_type'),
                DB::raw('v.plate as vehicle_plate'),
                DB::raw('v.economico as vehicle_economico'),
                DB::raw('v.foto_path as vehicle_foto_path'),
                DB::raw('v.photo_url as vehicle_photo_url'),

                $queueSub ? DB::raw('tq.stand_id as stand_id') : DB::raw('NULL as stand_id'),
                $queueSub ? DB::raw('tq.queue_pos as queue_pos') : DB::raw('NULL as queue_pos'),
                $queueSub ? DB::raw('tq.queue_status as queue_status') : DB::raw('NULL as queue_status'),
            ])
            ->orderBy('drivers.id')
            ->limit(500)
            ->get();

        return $rows->map(function ($r) {
            $driverFoto = !empty($r->driver_foto_path) ? Storage::url($r->driver_foto_path) : null;

            $vehFoto = null;
            if (!empty($r->vehicle_photo_url)) {
                $vehFoto = $r->vehicle_photo_url;
            } elseif (!empty($r->vehicle_foto_path)) {
                $vehFoto = Storage::url($r->vehicle_foto_path);
            }

            return [
                'driver_id'         => (int)$r->driver_id,
                'name'              => $r->name ?? null,

                'lat'               => isset($r->lat) ? (float)$r->lat : null,
                'lng'               => isset($r->lng) ? (float)$r->lng : null,
                'bearing'           => isset($r->bearing) ? (float)$r->bearing : null,
                'updated_at'        => $r->updated_at ?? null,
                'is_fresh'          => (int)($r->is_fresh ?? 0),

                'driver_status'     => $r->driver_status ?? null,
                'ride_status'       => $r->ride_status ?? null,
                'shift_open'        => (int)($r->shift_open ?? 0),

                'vehicle_type'      => $r->vehicle_type ?? 'sedan',
                'vehicle_plate'     => $r->vehicle_plate ?? null,
                'vehicle_economico' => $r->vehicle_economico ?? null,

                'stand_id'          => isset($r->stand_id) ? (int)$r->stand_id : null,
                'queue_pos'         => isset($r->queue_pos) ? (int)$r->queue_pos : null,
                'queue_status'      => $r->queue_status ?? null,

                'driver_photo_url'  => $driverFoto,
                'vehicle_photo_url' => $vehFoto,
            ];
        })->values()->all();
    }



private function queryActiveRides(int $tenantId, int $partnerId): array
{
    if (!Schema::hasTable('rides')) return [];

    $q = DB::table('rides as r')->where('r.tenant_id', $tenantId);

    // Activos (no finalizados)
    if (Schema::hasColumn('rides', 'status')) {
        $q->whereNotIn('r.status', ['finished','completed','canceled','cancelled','expired']);
    }

    // Joins (los usamos tanto para filtrar partner como para enriquecer)
    $joinV = Schema::hasTable('vehicles') && Schema::hasColumn('rides','vehicle_id');
    $joinD = Schema::hasTable('drivers')  && Schema::hasColumn('rides','driver_id');
    $joinP = Schema::hasTable('passengers');

    if ($joinV) {
        $q->leftJoin('vehicles as v', function($j) use ($tenantId) {
            $j->on('v.id', '=', 'r.vehicle_id');
            if (Schema::hasColumn('vehicles','tenant_id')) {
                $j->where('v.tenant_id', '=', $tenantId);
            }
        });
    }

    if ($joinD) {
        $q->leftJoin('drivers as d', function($j) use ($tenantId) {
            $j->on('d.id', '=', 'r.driver_id');
            if (Schema::hasColumn('drivers','tenant_id')) {
                $j->where('d.tenant_id', '=', $tenantId);
            }
        });
    }

    // Passenger join (prioridad: passenger_id)
    $joinedPassenger = false;
    if ($joinP) {
        if (Schema::hasColumn('rides','passenger_id')) {
            $q->leftJoin('passengers as p', function($j) use ($tenantId) {
                $j->on('p.id', '=', 'r.passenger_id')
                  ->where('p.tenant_id', '=', $tenantId);
            });
            $joinedPassenger = true;
        } elseif (Schema::hasColumn('rides','passenger_phone') && Schema::hasColumn('passengers','phone')) {
            $q->leftJoin('passengers as p', function($j) use ($tenantId) {
                $j->on('p.phone', '=', 'r.passenger_phone')
                  ->where('p.tenant_id', '=', $tenantId);
            });
            $joinedPassenger = true;
        } elseif (Schema::hasColumn('rides','passenger_firebase_uid') && Schema::hasColumn('passengers','firebase_uid')) {
            $q->leftJoin('passengers as p', function($j) use ($tenantId) {
                $j->on('p.firebase_uid', '=', 'r.passenger_firebase_uid')
                  ->where('p.tenant_id', '=', $tenantId);
            });
            $joinedPassenger = true;
        }
    }

    // Filtro partner (en este orden)
    if (Schema::hasColumn('rides','partner_id')) {
        $q->where('r.partner_id', $partnerId);
    } elseif ($joinV && Schema::hasColumn('vehicles','partner_id')) {
        $q->where('v.partner_id', $partnerId);
    } elseif ($joinD && Schema::hasColumn('drivers','partner_id')) {
        $q->where('d.partner_id', $partnerId);
    }

    // ----------------------------
    // Offer viva (última por ride)
    // ----------------------------
    $joinedOffer = false;
    if (Schema::hasTable('ride_offers') && Schema::hasColumn('ride_offers','ride_id')) {
        $sub = DB::table('ride_offers as ro2')
            ->selectRaw('ro2.ride_id, MAX(ro2.id) as max_offer_id')
            ->where('ro2.tenant_id', $tenantId);

        if (Schema::hasColumn('ride_offers','status')) {
            $sub->whereNotIn('ro2.status', ['expired','released','rejected','canceled','cancelled']);
        }

        $sub->groupBy('ro2.ride_id');

        $q->leftJoinSub($sub, 'rox', function($j) {
            $j->on('rox.ride_id', '=', 'r.id');
        });

        $q->leftJoin('ride_offers as ro', function($j) use ($tenantId) {
            $j->on('ro.id', '=', 'rox.max_offer_id');
            if (Schema::hasColumn('ride_offers','tenant_id')) {
                $j->where('ro.tenant_id', '=', $tenantId);
            }
        });

        $joinedOffer = true;
    }

    // ----------------------------
    // Resolver columnas flexibles
    // ----------------------------
    $pickLat = Schema::hasColumn('rides','pickup_lat') ? 'pickup_lat' : (Schema::hasColumn('rides','origin_lat') ? 'origin_lat' : null);
    $pickLng = Schema::hasColumn('rides','pickup_lng') ? 'pickup_lng' : (Schema::hasColumn('rides','origin_lng') ? 'origin_lng' : null);

    $dropLat = Schema::hasColumn('rides','drop_lat') ? 'drop_lat' : (Schema::hasColumn('rides','dest_lat') ? 'dest_lat' : (Schema::hasColumn('rides','destination_lat') ? 'destination_lat' : null));
    $dropLng = Schema::hasColumn('rides','drop_lng') ? 'drop_lng' : (Schema::hasColumn('rides','dest_lng') ? 'dest_lng' : (Schema::hasColumn('rides','destination_lng') ? 'destination_lng' : null));

    $originLabel = Schema::hasColumn('rides','origin_label') ? 'origin_label'
        : (Schema::hasColumn('rides','pickup_label') ? 'pickup_label'
        : (Schema::hasColumn('rides','pickup_address') ? 'pickup_address' : null));

    $destLabel = Schema::hasColumn('rides','dest_label') ? 'dest_label'
        : (Schema::hasColumn('rides','destination_label') ? 'destination_label'
        : (Schema::hasColumn('rides','drop_label') ? 'drop_label'
        : (Schema::hasColumn('rides','drop_address') ? 'drop_address' : null)));

    $polyCol = Schema::hasColumn('rides','route_polyline') ? 'route_polyline'
        : (Schema::hasColumn('rides','route_overview_polyline') ? 'route_overview_polyline'
        : (Schema::hasColumn('rides','polyline') ? 'polyline' : null));

    $polyPrecCol = Schema::hasColumn('rides','polyline_precision') ? 'polyline_precision'
        : (Schema::hasColumn('rides','polyline_prec') ? 'polyline_prec' : null);

    // Stops típicos (ajusta si tu schema usa otros nombres)
    $s1Lat = Schema::hasColumn('rides','stop1_lat') ? 'stop1_lat' : null;
    $s1Lng = Schema::hasColumn('rides','stop1_lng') ? 'stop1_lng' : null;
    $s1Lab = Schema::hasColumn('rides','stop1_label') ? 'stop1_label' : null;

    $s2Lat = Schema::hasColumn('rides','stop2_lat') ? 'stop2_lat' : null;
    $s2Lng = Schema::hasColumn('rides','stop2_lng') ? 'stop2_lng' : null;
    $s2Lab = Schema::hasColumn('rides','stop2_label') ? 'stop2_label' : null;

    // ----------------------------
    // SELECT
    // ----------------------------
    $select = ['r.id as ride_id'];

    if (Schema::hasColumn('rides','status')) $select[] = 'r.status';

    if ($pickLat) $select[] = "r.$pickLat as pickup_lat";
    if ($pickLng) $select[] = "r.$pickLng as pickup_lng";
    if ($dropLat) $select[] = "r.$dropLat as drop_lat";
    if ($dropLng) $select[] = "r.$dropLng as drop_lng";

    if ($originLabel) $select[] = "r.$originLabel as origin_label";
    if ($destLabel)   $select[] = "r.$destLabel as dest_label";

    if ($s1Lat) $select[] = "r.$s1Lat as stop1_lat";
    if ($s1Lng) $select[] = "r.$s1Lng as stop1_lng";
    if ($s1Lab) $select[] = "r.$s1Lab as stop1_label";

    if ($s2Lat) $select[] = "r.$s2Lat as stop2_lat";
    if ($s2Lng) $select[] = "r.$s2Lng as stop2_lng";
    if ($s2Lab) $select[] = "r.$s2Lab as stop2_label";

    if ($polyCol)     $select[] = "r.$polyCol as route_polyline";
    if ($polyPrecCol) $select[] = "r.$polyPrecCol as polyline_precision";

    if (Schema::hasColumn('rides','distance_m')) $select[] = 'r.distance_m';
    if (Schema::hasColumn('rides','duration_s')) $select[] = 'r.duration_s';

    if (Schema::hasColumn('rides','driver_id'))  $select[] = 'r.driver_id';
    if (Schema::hasColumn('rides','vehicle_id')) $select[] = 'r.vehicle_id';

    if (Schema::hasColumn('rides','quoted_amount')) $select[] = 'r.quoted_amount';
    elseif (Schema::hasColumn('rides','amount'))    $select[] = 'r.amount as quoted_amount';

    if (Schema::hasColumn('rides','agreed_amount')) $select[] = 'r.agreed_amount';
    if (Schema::hasColumn('rides','total_amount'))  $select[] = 'r.total_amount';

    // Passenger name/phone (fallback)
    if (Schema::hasColumn('rides','passenger_name')) $select[] = 'r.passenger_name';
    elseif (Schema::hasColumn('rides','pax_name'))   $select[] = 'r.pax_name as passenger_name';

    if (Schema::hasColumn('rides','passenger_phone')) $select[] = 'r.passenger_phone';

    // Passenger avatar/phone desde passengers
    if ($joinedPassenger) {
        if (Schema::hasColumn('passengers','avatar_url')) $select[] = 'p.avatar_url as passenger_avatar_url';
        if (Schema::hasColumn('passengers','phone'))      $select[] = 'p.phone as passenger_phone_join';
        if (!Schema::hasColumn('rides','passenger_name') && Schema::hasColumn('passengers','name')) {
            $select[] = 'p.name as passenger_name_join';
        } elseif (Schema::hasColumn('passengers','name')) {
            $select[] = 'p.name as passenger_name_join';
        }
    }

    // Driver/Vehicle info (para card)
    if ($joinD && Schema::hasColumn('drivers','name'))  $select[] = 'd.name as driver_name';
    if ($joinD && Schema::hasColumn('drivers','phone')) $select[] = 'd.phone as driver_phone';

    if ($joinV && Schema::hasColumn('vehicles','economico')) $select[] = 'v.economico as vehicle_economico';
    if ($joinV && Schema::hasColumn('vehicles','plate'))     $select[] = 'v.plate as vehicle_plate';

    // Timestamps para timeline
    foreach (['created_at','requested_at','accepted_at','arrived_at','on_board_at','boarded_at','finished_at','canceled_at','updated_at'] as $col) {
        if (Schema::hasColumn('rides', $col)) $select[] = "r.$col";
    }

    // Oferta viva (si existe)
    if ($joinedOffer) {
        if (Schema::hasColumn('ride_offers','id'))       $select[] = 'ro.id as offer_id';
        if (Schema::hasColumn('ride_offers','status'))   $select[] = 'ro.status as offer_status';
        if (Schema::hasColumn('ride_offers','sent_at'))  $select[] = 'ro.sent_at as offer_sent_at';
        if (Schema::hasColumn('ride_offers','expires_at')) $select[] = 'ro.expires_at as offer_expires_at';
        if (Schema::hasColumn('ride_offers','driver_offer')) $select[] = 'ro.driver_offer';
        if (Schema::hasColumn('ride_offers','driver_id')) $select[] = 'ro.driver_id as offer_driver_id';
        if (Schema::hasColumn('ride_offers','vehicle_id')) $select[] = 'ro.vehicle_id as offer_vehicle_id';
    }

    $rows = $q->select($select)
        ->orderByDesc('r.id')
        ->limit(200)
        ->get();

    return $rows->map(function($r) {
        $passName = $r->passenger_name ?? $r->passenger_name_join ?? null;
        $passPhone = $r->passenger_phone ?? $r->passenger_phone_join ?? null;

        return [
            'ride_id'   => (int)($r->ride_id ?? 0),
            'status'    => $r->status ?? null,

            'pickup_lat'=> isset($r->pickup_lat) ? (float)$r->pickup_lat : null,
            'pickup_lng'=> isset($r->pickup_lng) ? (float)$r->pickup_lng : null,
            'drop_lat'  => isset($r->drop_lat) ? (float)$r->drop_lat : null,
            'drop_lng'  => isset($r->drop_lng) ? (float)$r->drop_lng : null,

            'origin_label' => $r->origin_label ?? null,
            'dest_label'   => $r->dest_label ?? null,

            'stop1_lat'   => isset($r->stop1_lat) ? (float)$r->stop1_lat : null,
            'stop1_lng'   => isset($r->stop1_lng) ? (float)$r->stop1_lng : null,
            'stop1_label' => $r->stop1_label ?? null,

            'stop2_lat'   => isset($r->stop2_lat) ? (float)$r->stop2_lat : null,
            'stop2_lng'   => isset($r->stop2_lng) ? (float)$r->stop2_lng : null,
            'stop2_label' => $r->stop2_label ?? null,

            'route_polyline'     => $r->route_polyline ?? null,
            'polyline_precision' => isset($r->polyline_precision) ? (int)$r->polyline_precision : 5,

            'distance_m' => isset($r->distance_m) ? (int)$r->distance_m : null,
            'duration_s' => isset($r->duration_s) ? (int)$r->duration_s : null,

            'driver_id'  => isset($r->driver_id) ? (int)$r->driver_id : null,
            'vehicle_id' => isset($r->vehicle_id) ? (int)$r->vehicle_id : null,

            'driver_name' => $r->driver_name ?? null,
            'driver_phone'=> $r->driver_phone ?? null,

            'vehicle_economico' => $r->vehicle_economico ?? null,
            'vehicle_plate'     => $r->vehicle_plate ?? null,

            'quoted_amount' => isset($r->quoted_amount) ? (float)$r->quoted_amount : null,
            'agreed_amount' => isset($r->agreed_amount) ? (float)$r->agreed_amount : null,
            'total_amount'  => isset($r->total_amount) ? (float)$r->total_amount : null,

            'passenger_name' => $passName,
            'passenger_phone'=> $passPhone,
            'passenger_avatar_url' => $r->passenger_avatar_url ?? null,

            'created_at'   => $r->created_at ?? null,
            'requested_at' => $r->requested_at ?? null,
            'accepted_at'  => $r->accepted_at ?? null,
            'arrived_at'   => $r->arrived_at ?? null,
            'on_board_at'  => $r->on_board_at ?? ($r->boarded_at ?? null),
            'finished_at'  => $r->finished_at ?? null,
            'canceled_at'  => $r->canceled_at ?? null,
            'updated_at'   => $r->updated_at ?? null,

            // Offer viva (si existe)
            'offer_id'         => isset($r->offer_id) ? (int)$r->offer_id : null,
            'offer_status'     => $r->offer_status ?? null,
            'offer_sent_at'    => $r->offer_sent_at ?? null,
            'offer_expires_at' => $r->offer_expires_at ?? null,
            'driver_offer'     => isset($r->driver_offer) ? (float)$r->driver_offer : null,
            'offer_driver_id'  => isset($r->offer_driver_id) ? (int)$r->offer_driver_id : null,
            'offer_vehicle_id' => isset($r->offer_vehicle_id) ? (int)$r->offer_vehicle_id : null,
        ];
    })->values()->all();
}

private function ridesBaseQuery(int $tenantId, int $partnerId)
{
    $q = DB::table('rides as r')
        ->where('r.tenant_id', $tenantId);

    // Solo activos (si existe status)
    if (Schema::hasColumn('rides', 'status')) {
        $q->whereNotIn('r.status', ['finished','completed','canceled','cancelled','expired']);
    }

    // Filtro partner: preferir rides.partner_id si existe
    if (Schema::hasColumn('rides', 'partner_id')) {
        $q->where('r.partner_id', $partnerId);
        return $q;
    }

    // Si no existe partner_id en rides: filtrar por vehicle.partner_id o driver.partner_id
    if (Schema::hasTable('vehicles') && Schema::hasColumn('rides','vehicle_id') && Schema::hasColumn('vehicles','partner_id')) {
        $q->whereExists(function ($x) use ($partnerId) {
            $x->selectRaw('1')
              ->from('vehicles as v')
              ->whereColumn('v.id', 'r.vehicle_id')
              ->where('v.partner_id', $partnerId);
        });
        return $q;
    }

    if (Schema::hasTable('drivers') && Schema::hasColumn('rides','driver_id') && Schema::hasColumn('drivers','partner_id')) {
        $q->whereExists(function ($x) use ($partnerId) {
            $x->selectRaw('1')
              ->from('drivers as d')
              ->whereColumn('d.id', 'r.driver_id')
              ->where('d.partner_id', $partnerId);
        });
        return $q;
    }

    // Si no hay forma de filtrar, devuelve vacío “seguro”
    $q->whereRaw('1=0');
    return $q;
}


public function rideShow(Request $request, int $rideId)
{
    [$tenantId, $partnerId] = $this->ctx($request);

    if (!Schema::hasTable('rides')) {
        return response()->json(['ok' => false, 'msg' => 'Tabla rides no existe.'], 404);
    }

    $q = $this->ridesBaseQuery($tenantId, $partnerId)
        ->where('r.id', $rideId);

    // joins opcionales
    if (Schema::hasTable('drivers') && Schema::hasColumn('rides','driver_id')) {
        $q->leftJoin('drivers as d', function ($j) use ($tenantId) {
            $j->on('d.id','=','r.driver_id')->where('d.tenant_id','=',$tenantId);
        });
    }

    if (Schema::hasTable('vehicles') && Schema::hasColumn('rides','vehicle_id')) {
        $q->leftJoin('vehicles as v', function ($j) use ($tenantId) {
            $j->on('v.id','=','r.vehicle_id')->where('v.tenant_id','=',$tenantId);
        });
    }

    $joinedPassenger = false;
    if (
        Schema::hasTable('passengers')
        && Schema::hasColumn('rides','passenger_id')
    ) {
        $joinedPassenger = true;
        $q->leftJoin('passengers as p', function ($j) use ($tenantId) {
            $j->on('p.id','=','r.passenger_id')->where('p.tenant_id','=',$tenantId);
        });
    }

    // Select robusto (strings + DB::raw)
    $sel = [
        'r.id as ride_id',

        Schema::hasColumn('rides','status') ? 'r.status' : DB::raw("NULL as status"),
        Schema::hasColumn('rides','quoted_amount') ? 'r.quoted_amount' : (Schema::hasColumn('rides','amount') ? 'r.amount as quoted_amount' : DB::raw("NULL as quoted_amount")),
        Schema::hasColumn('rides','currency') ? 'r.currency' : DB::raw("'MXN' as currency"),

        // coords normalizadas
        Schema::hasColumn('rides','pickup_lat') ? 'r.pickup_lat' : (Schema::hasColumn('rides','origin_lat') ? 'r.origin_lat as pickup_lat' : DB::raw("NULL as pickup_lat")),
        Schema::hasColumn('rides','pickup_lng') ? 'r.pickup_lng' : (Schema::hasColumn('rides','origin_lng') ? 'r.origin_lng as pickup_lng' : DB::raw("NULL as pickup_lng")),

        Schema::hasColumn('rides','drop_lat') ? 'r.drop_lat'
            : (Schema::hasColumn('rides','dest_lat') ? 'r.dest_lat as drop_lat'
            : (Schema::hasColumn('rides','destination_lat') ? 'r.destination_lat as drop_lat' : DB::raw("NULL as drop_lat"))),

        Schema::hasColumn('rides','drop_lng') ? 'r.drop_lng'
            : (Schema::hasColumn('rides','dest_lng') ? 'r.dest_lng as drop_lng'
            : (Schema::hasColumn('rides','destination_lng') ? 'r.destination_lng as drop_lng' : DB::raw("NULL as drop_lng"))),

        Schema::hasColumn('rides','origin_label') ? 'r.origin_label' : DB::raw("NULL as origin_label"),
        Schema::hasColumn('rides','dest_label')   ? 'r.dest_label'   : DB::raw("NULL as dest_label"),

        // polyline guardada si existe (ajusta nombres reales si los tienes)
        Schema::hasColumn('rides','route_polyline') ? 'r.route_polyline'
            : (Schema::hasColumn('rides','route_polyline_enc') ? 'r.route_polyline_enc as route_polyline' : DB::raw("NULL as route_polyline")),

        // stops si existen
        Schema::hasColumn('rides','stops') ? 'r.stops'
            : (Schema::hasColumn('rides','stops_json') ? 'r.stops_json as stops' : DB::raw("NULL as stops")),

        Schema::hasColumn('rides','updated_at')    ? 'r.updated_at'    : DB::raw("NULL as updated_at"),
        Schema::hasColumn('rides','requested_at')  ? 'r.requested_at'  : DB::raw("NULL as requested_at"),
        Schema::hasColumn('rides','accepted_at')   ? 'r.accepted_at'   : DB::raw("NULL as accepted_at"),
        Schema::hasColumn('rides','arrived_at')    ? 'r.arrived_at'    : DB::raw("NULL as arrived_at"),
        Schema::hasColumn('rides','on_board_at')   ? 'r.on_board_at'   : DB::raw("NULL as on_board_at"),
        Schema::hasColumn('rides','finished_at')   ? 'r.finished_at'   : DB::raw("NULL as finished_at"),
        Schema::hasColumn('rides','canceled_at')   ? 'r.canceled_at'   : DB::raw("NULL as canceled_at"),
    ];

    // Enriquecidos (si hubo join)
    if (Schema::hasTable('drivers') && Schema::hasColumn('rides','driver_id')) {
        $sel[] = DB::raw("COALESCE(d.name, CONCAT('Driver #', r.driver_id)) as driver_name");
        $sel[] = DB::raw("d.phone as driver_phone");
    }

    if (Schema::hasTable('vehicles') && Schema::hasColumn('rides','vehicle_id')) {
        $sel[] = DB::raw("v.economico as vehicle_economico");
        $sel[] = DB::raw("v.plate as vehicle_plate");
        $sel[] = DB::raw("v.brand as vehicle_brand");
        $sel[] = DB::raw("v.model as vehicle_model");
        $sel[] = DB::raw("v.type as vehicle_type");
        $sel[] = DB::raw("v.color as vehicle_color");
        $sel[] = DB::raw("v.year as vehicle_year");
        $sel[] = DB::raw("v.photo_url as vehicle_photo_url");
    }

    if ($joinedPassenger) {
        $sel[] = DB::raw("COALESCE(p.name, r.passenger_name, 'Pasajero') as passenger_name");
        $sel[] = DB::raw("COALESCE(p.phone, r.passenger_phone) as passenger_phone");
        $sel[] = DB::raw("p.avatar_url as passenger_avatar_url");
    } else {
        $sel[] = Schema::hasColumn('rides','passenger_name') ? 'r.passenger_name' : DB::raw("'Pasajero' as passenger_name");
        $sel[] = Schema::hasColumn('rides','passenger_phone') ? 'r.passenger_phone' : DB::raw("NULL as passenger_phone");
        $sel[] = DB::raw("NULL as passenger_avatar_url");
    }

    $row = $q->select($sel)->first();
    abort_if(!$row, 404, 'Ride no encontrado para este partner.');

    // ---- route polyline: si falta, calcular con Google y cachear 5 min ----
    $olat = $row->pickup_lat ?? null;
    $olng = $row->pickup_lng ?? null;
    $dlat = $row->drop_lat ?? null;
    $dlng = $row->drop_lng ?? null;

    $poly = $row->route_polyline ?? null;
    $distance_m = property_exists($row,'distance_m') ? $row->distance_m : null;
    $duration_s = property_exists($row,'duration_s') ? $row->duration_s : null;

    if (empty($poly) && $olat && $olng && $dlat && $dlng && config('services.google.key')) {
        $cacheKey = "pm:route:$tenantId:$rideId:" . md5("$olat,$olng|$dlat,$dlng");
        $rt = Cache::remember($cacheKey, 300, function () use ($olat,$olng,$dlat,$dlng) {
            $gm = app(GoogleMapsService::class);
            return $gm->route((float)$olat,(float)$olng,(float)$dlat,(float)$dlng);
        });

        $poly = $rt['polyline'] ?? null;
        $distance_m = $rt['distance_m'] ?? $distance_m;
        $duration_s = $rt['duration_s'] ?? $duration_s;
    }

    // Oferta principal (accepted primero, si no última offered)
   // Oferta “principal” (accepted primero, si no la última offered)
$offer = null;

if (Schema::hasTable('ride_offers')) {
    $oq = DB::table('ride_offers as ro')
        ->where('ro.tenant_id', $tenantId)
        ->where('ro.ride_id', $rideId);

    // acotar al partner si se puede
    if (Schema::hasTable('drivers') && Schema::hasColumn('ride_offers','driver_id') && Schema::hasColumn('drivers','partner_id')) {
        $oq->whereExists(function($x) use ($partnerId){
            $x->selectRaw('1')
              ->from('drivers as d2')
              ->whereColumn('d2.id','ro.driver_id')
              ->where('d2.partner_id',$partnerId);
        });
    }

    $offerSel = [
        'ro.id as offer_id',
        Schema::hasColumn('ride_offers','status') ? 'ro.status as offer_status' : DB::raw("NULL as offer_status"),
        Schema::hasColumn('ride_offers','driver_id') ? 'ro.driver_id' : DB::raw("NULL as driver_id"),
        Schema::hasColumn('ride_offers','vehicle_id') ? 'ro.vehicle_id' : DB::raw("NULL as vehicle_id"),
        Schema::hasColumn('ride_offers','driver_offer') ? 'ro.driver_offer' : DB::raw("NULL as driver_offer"),
        Schema::hasColumn('ride_offers','sent_at') ? 'ro.sent_at' : DB::raw("NULL as sent_at"),
        Schema::hasColumn('ride_offers','expires_at') ? 'ro.expires_at' : DB::raw("NULL as expires_at"),

        // accepted_at NO existe en tu tabla -> fallback (si tienes responded_at u otro)
        Schema::hasColumn('ride_offers','accepted_at')
            ? 'ro.accepted_at'
            : (Schema::hasColumn('ride_offers','responded_at')
                ? 'ro.responded_at as accepted_at'
                : DB::raw("NULL as accepted_at")),
    ];

    $oq->select($offerSel);

    // Orden robusto (si no hay sent_at, usa id)
    if (Schema::hasColumn('ride_offers','status')) {
        $oq->orderByRaw("CASE ro.status WHEN 'accepted' THEN 0 WHEN 'offered' THEN 1 ELSE 2 END");
    }
    if (Schema::hasColumn('ride_offers','sent_at')) $oq->orderByDesc('ro.sent_at');
    else $oq->orderByDesc('ro.id');

    $offer = $oq->first();
}


    $rideArr = (array)$row;
    $rideArr['route_polyline'] = $poly;
    $rideArr['distance_m'] = $distance_m;
    $rideArr['duration_s'] = $duration_s;

    return response()->json([
        'ok'          => true,
        'server_time' => now()->toDateTimeString(),
        'ride'        => $rideArr,
        'offer'       => $offer,
    ]);
}



    /**
     * Bases del tenant (y queue_count si existe taxi_stand_queue)
     */
    private function queryTaxiStands(int $tenantId, int $partnerId): array
    {
        if (!Schema::hasTable('taxi_stands')) return [];

        $q = DB::table('taxi_stands as s')->where('s.tenant_id', $tenantId);
        if (Schema::hasColumn('taxi_stands', 'activo')) $q->where('s.activo', 1);

        $qc = null;
        if (Schema::hasTable('taxi_stand_queue') && Schema::hasColumn('taxi_stand_queue', 'stand_id')) {
            $qc0 = DB::table('taxi_stand_queue as q')
                ->select('q.stand_id', DB::raw('COUNT(*) as queue_count'))
                ->where('q.tenant_id', $tenantId);

            if (Schema::hasColumn('taxi_stand_queue', 'left_at')) $qc0->whereNull('q.left_at');
            if (Schema::hasColumn('taxi_stand_queue', 'status')) {
                $qc0->whereIn(DB::raw('LOWER(q.status)'), ['en_cola']);
            }

            $qc0->groupBy('q.stand_id');
            $qc = $qc0;
        }

        if ($qc) {
            $q->leftJoinSub($qc, 'qc', function ($j) {
                $j->on('qc.stand_id', '=', 's.id');
            });
        }

        $rows = $q->select([
                's.id as stand_id',
                Schema::hasColumn('taxi_stands', 'nombre')   ? 's.nombre'   : DB::raw('NULL as name'),
                Schema::hasColumn('taxi_stands', 'codigo') ? 's.codigo' : DB::raw('NULL as codigo'),
                Schema::hasColumn('taxi_stands', 'lat')    ? 's.lat'    : DB::raw('NULL as lat'),
                Schema::hasColumn('taxi_stands', 'lng')    ? 's.lng'    : DB::raw('NULL as lng'),
                DB::raw('COALESCE(qc.queue_count,0) as queue_count'),
            ])
            ->orderByRaw('COALESCE(s.nombre, s.codigo, s.id)')
            ->limit(300)
            ->get();

        return $rows->map(fn($r) => [
            'stand_id'    => (int)$r->stand_id,
            'name'        => $r->nombre ?: ($r->codigo ?: ('Base #'.$r->stand_id)),
            'codigo'      => $r->codigo ?? null,
            'lat'         => isset($r->lat) ? (float)$r->lat : null,
            'lng'         => isset($r->lng) ? (float)$r->lng : null,
            'queue_count' => (int)($r->queue_count ?? 0),
        ])->values()->all();
    }
}
