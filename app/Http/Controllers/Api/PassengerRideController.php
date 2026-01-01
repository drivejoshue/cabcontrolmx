<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Passenger;
use App\Models\Ride;
use App\Services\TenantResolverService;
use App\Services\FareQuoteService;
use App\Services\CreateRideService;
use App\Services\QuoteRecalcService;
use App\Services\RideBroadcaster;
use Illuminate\Http\Request;
use App\Services\OfferBroadcaster; // si no lo tienes ya
use App\Services\Realtime; 
use App\Models\Rating;

use App\Services\AutoDispatchService;


class PassengerRideController extends Controller
{
    /**
     * /api/passenger/quote
     */
 

    /**
     * /api/passenger/rides
     * Crea ride reutilizando CreateRideService y el esquema de rides actual.
     */
    public function store(
        Request $req,
        TenantResolverService $tenantResolver,
        FareQuoteService $fareQuote,
        CreateRideService $createRide,
        QuoteRecalcService $quoteRecalc,
    )
    {
        // 🔹 0) LOG DE ENTRADA CRUDA
        \Log::info('PassengerRide.store IN', [
            'payload' => $req->all(),
            'headers' => $req->headers->all(),
        ]);

        $v = $req->validate([
            'tenant_id'      => 'required|integer|exists:tenants,id',

            'pickup_lat'     => 'required|numeric',
            'pickup_lng'     => 'required|numeric',
            'pickup_address' => 'nullable|string|max:160',

            'dest_lat'       => 'required|numeric',
            'dest_lng'       => 'required|numeric',
            'dest_address'   => 'nullable|string|max:160',

            'passengers'     => 'required|integer|min:1|max:6',

            'notes'          => 'nullable|array|max:5',
            'notes.*'        => 'string|max:80',
            'payment_method' => 'nullable|in:cash,transfer,card,corp',

            'stops'          => 'nullable|array|max:2',
            'stops.*.lat'    => 'required_with:stops|numeric',
            'stops.*.lng'    => 'required_with:stops|numeric',
            'stops.*.label'  => 'nullable|string|max:160',

            'offered_fare'   => 'required|integer|min:10',
            'firebase_uid'   => 'required|string|max:128',
        ]);

        // 🔹 1) LOG DESPUÉS DE VALIDAR
        \Log::info('PassengerRide.store VALIDATED', [
            'v' => $v,
        ]);

        /** @var \App\Models\Tenant $tenant */
        $tenant = Tenant::findOrFail($v['tenant_id']);
        $tenantId = (int) $tenant->id;

        // 1) Validar cobertura
        $coverageTenant = $tenantResolver->resolveForPickupPoint(
            (float) $v['pickup_lat'],
            (float) $v['pickup_lng'],
        );

       if (! $coverageTenant) {
       return response()->json([
        'ok'   => false,
        'code' => 'NO_COVERAGE',
        'msg'  => 'Sin cobertura en tu zona. Intenta en otra ubicación.',
            ], 422);
        }

        if ((int)$coverageTenant->id !== $tenantId) {
            return response()->json([
                'ok'   => false,
                'code' => 'COVERAGE_MISMATCH',
                'msg'  => 'La ubicación de recogida no coincide con la zona del operador.',
                'expected_tenant_id' => (int)$coverageTenant->id,
            ], 422);
        }


        // 2) Buscar pasajero
        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (! $passenger) {
            \Log::warning('PassengerRide.store passenger not found', [
                'firebase_uid' => $v['firebase_uid'],
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
            ], 422);
        }
        // 2.b) Evitar más de un viaje activo por pasajero
        $activeStatuses = [
            'requested', 'searching', 'bidding', 'pending',
            'accepted', 'assigned', 'en_route',
            'driver_arrived', 'on_board',
        ];

        $existing = DB::table('rides')
            ->where('passenger_id', $passenger->id)
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return response()->json([
                'ok'   => false,
                'code' => 'ride_active',
                'msg'  => 'Ya tienes un viaje activo.',
                'ride' => [
                    'id'        => (int) $existing->id,
                    'tenant_id' => (int) $existing->tenant_id,
                    'status'    => $existing->status,
                ],
            ], 409);
        }


        // 3) Origen/destino y stops
        $origin = [
            'lat' => (float) $v['pickup_lat'],
            'lng' => (float) $v['pickup_lng'],
        ];
        $destination = [
            'lat' => (float) $v['dest_lat'],
            'lng' => (float) $v['dest_lng'],
        ];

        $stopsIn = $v['stops'] ?? [];
        $stopsForQuote = [];
        foreach ($stopsIn as $s) {
            $stopsForQuote[] = [
                'lat' => (float) $s['lat'],
                'lng' => (float) $s['lng'],
            ];
        }

        // 4) Recalcular quote
        $quote = $fareQuote->quoteForTenantAndPoints(
            tenantId:    $tenantId,
            origin:      $origin,
            destination: $destination,
            stops:       $stopsForQuote,
            roundToStep: null,
        );

        $recommended = (int) ($quote['amount'] ?? 0);
        if ($recommended < 20) {
            $recommended = 20;
        }

        $distance_m  = isset($quote['distance_m']) ? (int) $quote['distance_m'] : null;
        $duration_s  = isset($quote['duration_s']) ? (int) $quote['duration_s'] : null;
        $route_poly  = $quote['route_polyline'] ?? null;

        // 🔹 LOG DEL QUOTE
        \Log::info('PassengerRide.store QUOTE', [
            'tenant_id'   => $tenantId,
            'quote_raw'   => $quote,
            'recommended' => $recommended,
            'distance_m'  => $distance_m,
            'duration_s'  => $duration_s,
        ]);

       // 5) Rango de oferta pasajero
            //    - mínimo: 90% de la recomendada (o 10 como piso absoluto)
            //    - sin tope máximo duro: permitimos que el pasajero ofrezca más
            $minAllowed = (int) floor($recommended * 0.80);
            if ($minAllowed < 20) {
                $minAllowed = 20;
            }

            $offered = (int) $v['offered_fare'];

            if ($offered < $minAllowed) {
                $offered = $minAllowed;
            }

            // (Opcional) Solo para logging, por si quieres monitorear "ofertas muy altas"
            $maxSoft = (int) ceil($recommended * 1.);
            \Log::info('PassengerRide.store OFFER_CHECK', [
                'recommended' => $recommended,
                'minAllowed'  => $minAllowed,
                'maxSoft'     => $maxSoft,
                'offered_in'  => (int) $v['offered_fare'],
                'offered_final' => $offered,
            ]);

        // 6) notes[] → string
        $notesText = null;
        if (! empty($v['notes'])) {
            $notesText = implode(' | ', $v['notes']);
        }

        $paymentMethod = $v['payment_method'] ?? 'cash';
        if (! in_array($paymentMethod, ['cash','transfer','card','corp'], true)) {
            $paymentMethod = 'cash';
        }

        // 7) Data para CreateRideService
        $data = [
            'passenger_name'  => $passenger->name,
            'passenger_phone' => $passenger->phone,

            'origin_label' => $v['pickup_address'] ?? null,
            'origin_lat'   => $v['pickup_lat'],
            'origin_lng'   => $v['pickup_lng'],

            'dest_label' => $v['dest_address'] ?? null,
            'dest_lat'   => $v['dest_lat'],
            'dest_lng'   => $v['dest_lng'],

            'stops' => $stopsIn,

            'payment_method'   => $paymentMethod,
            'fare_mode'        => 'fixed',
            'notes'            => $notesText,
            'pax'              => $v['passengers'],
            'scheduled_for'    => null,

            'quoted_amount'     => $recommended,
            'distance_m'        => $distance_m,
            'duration_s'        => $duration_s,
            'route_polyline'    => $route_poly,
            'requested_channel' => 'passenger_app',
        ];

        // 🔹 LOG DE LOS DATOS QUE SE MANDAN A CreateRideService
        \Log::info('PassengerRide.store DATA_BEFORE_CREATE', [
            'tenant_id'    => $tenantId,
            'passenger_id' => $passenger->id,
            'offered'      => $offered,
            'data'         => $data,
        ]);

        DB::beginTransaction();
        try {
            // 8) Crear ride
            $ride = $createRide->create($data, $tenantId);

            \Log::info('PassengerRide.store RIDE_CREATED', [
                'tenant_id' => $tenantId,
                'ride_id'   => $ride->id,
            ]);

            // 9) Guardar stops_json + recalc
            $stops = $data['stops'] ?? [];
            if (! empty($stops)) {
                $stops = array_values(array_slice(array_map(function ($s) {
                    return [
                        'lat'   => isset($s['lat'])   ? (float) $s['lat']   : null,
                        'lng'   => isset($s['lng'])   ? (float) $s['lng']   : null,
                        'label' => isset($s['label']) ? (trim((string) $s['label']) ?: null) : null,
                    ];
                }, $stops), 0, 2));

                DB::table('rides')
                    ->where('tenant_id', $tenantId)->where('id', $ride->id)
                    ->update([
                        'stops_json'  => json_encode($stops),
                        'stops_count' => count($stops),
                        'stop_index'  => 0,
                        'updated_at'  => now(),
                    ]);

                $quoteRecalc->recalcWithStops($ride->id, $tenantId);

                DB::table('ride_status_history')->insert([
                    'tenant_id'   => $tenantId,
                    'ride_id'     => $ride->id,
                    'prev_status' => null,
                    'new_status'  => 'stops_set',
                    'meta'        => json_encode(['count' => count($stops), 'stops' => $stops]),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // 10) Campos específicos de marketplace/passenger
            DB::table('rides')
                ->where('tenant_id', $tenantId)->where('id', $ride->id)
                ->update([
                    'passenger_id'      => $passenger->id,
                    'allow_bidding'     => 1,
                    'passenger_offer'   => $offered,
                    'agreed_amount'     => $offered,
                    'requested_channel' => 'passenger_app',
                    'updated_at'        => now(),
                ]);

            DB::commit();

            // 🔹 LOG: ride ya está en DB y COMMIT hecho
            $rideId = (int) $ride->id;
            \Log::info('PassengerRide.store RIDE_COMMITTED', [
                'tenant_id' => $tenantId,
                'ride_id'   => $rideId,
                'offered'   => $offered,
                'recommended' => $recommended,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('PassengerRide.store EXCEPTION_DB', [
                'msg'   => $e->getMessage(),
                'code'  => $e->getCode(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => $e->getMessage(),
            ], 500);
        }

        // 🔹 11) Auto-dispatch + broadcast
        try {
            // Settings de dispatch
            $cfg = AutoDispatchService::settings($tenantId);

            $km         = (float)($cfg->radius_km ?? 3.0);
            $limitN     = (int)  ($cfg->limit_n ?? 3);
            $expires    = (int)  ($cfg->expires_s ?? 45); // p.ej. 45s
            $autoAssign = (bool)($cfg->auto_assign_if_single ?? false);

            $rideRow = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $rideId)
                ->first();


               


            if ($rideRow) {
                $dispatchRes = AutoDispatchService::kickoff(
                    tenantId: $tenantId,
                    rideId:   $rideId,
                    lat:      (float) $rideRow->origin_lat,
                    lng:      (float) $rideRow->origin_lng,
                    km:       $km,
                    expires:  $expires,
                    limitN:   $limitN,
                    autoAssignIfSingle: $autoAssign
                );

               

                \Log::info('PassengerRide.store kickoff', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $rideId,
                    'km'        => $km,
                    'limit_n'   => $limitN,
                    'expires'   => $expires,
                    'res'       => $dispatchRes,
                ]);
            } else {
                \Log::warning('PassengerRide.store kickoff: rideRow not found after commit', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $rideId,
                ]);
            }




            // 12) Broadcast "buscando conductor" al canal del ride
            RideBroadcaster::requestedFromPassenger(
                tenantId:       $tenantId,
                rideId:         (int) $rideId,
                recommended:    $recommended,
                passengerOffer: $offered,
                distanceM:      $distance_m,
                durationS:      $duration_s,
            );

            \Log::info('PassengerRide.store BROADCAST_DONE', [
                'tenant_id' => $tenantId,
                'ride_id'   => $rideId,
            ]);

        } catch (\Throwable $e) {
            // 👇 SI FALLA AQUÍ, YA HAY RIDE EN DB; SOLO LOGUEAMOS,
            // para que el pasajero NO vea 500 solo por el broadcast.
            \Log::error('PassengerRide.store EXCEPTION_POST_COMMIT', [
                'msg'   => $e->getMessage(),
                'code'  => $e->getCode(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // No lanzamos 500: respondemos ok igualmente.
        }

        return response()->json([
            'ok'              => true,
            'ride_id'         => (int) $rideId,
            'tenant_id'       => $tenantId,
            'passenger_id'    => (int) $passenger->id,
            'status'          => 'requested',
            'recommended'     => $recommended,
            'passenger_offer' => $offered,
            'distance_m'      => $distance_m,
            'duration_s'      => $duration_s,
           
        ], 201);
    }





    public function current(Request $req)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string',
        ]);

        $tenantId    = (int) $v['tenant_id'];
        $firebaseUid = $v['firebase_uid'];

        // 1) Resolver pasajero por firebase_uid
        $passenger = Passenger::where('firebase_uid', $firebaseUid)->first();
        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
            ], 422);
        }

        // 2) Estados que consideramos "ride activo" para el pasajero
        $activeStatuses = [
            'requested',       // recién creado, buscando conductor
            'searching',       // si lo llegas a usar
            'bidding',         // ola de ofertas
            'pending',         // variación si la usas
            'accepted',        // ya hay conductor asignado
            'assigned',
            'en_route',        // conductor en camino
            'arrived',  // conductor llegó al pickup
            'on_board',        // viaje en curso
        ];

        // 3) Buscar el ride más reciente de este pasajero en estado activo
        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('passenger_id', $passenger->id)
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('id')
            ->select([
                'id',
                'tenant_id',
                'passenger_id',
                'status',
                'requested_channel',
                'passenger_name',
                'passenger_phone',

                'origin_label',
                'origin_lat',
                'origin_lng',

                'dest_label',
                'dest_lat',
                'dest_lng',

                'distance_m',
                'duration_s',
                'quoted_amount',
                'passenger_offer',
                'driver_offer',
                'agreed_amount',
                'total_amount',
                'payment_method', 
                'driver_id',
                 'cancel_reason',
                 'canceled_by',

                          
                'passenger_onway_at',
                'passenger_onboard_at',
                'passenger_finished_at',

                'created_at',
                'updated_at',
            ])
            ->first();

          


        // 4) Si no hay ride activo → ok:true, ride:null
        if (! $row) {
            return response()->json([
                'ok'   => true,
                'ride' => null,
            ]);
        }

          $searchExpiresAt = $this->computeRideSearchExpiresAt($tenantId, (int)$row->id);

        // 5) Armar payload compacto para el cliente
        $ridePayload = [
            'id'               => (int) $row->id,
            'tenant_id'        => (int) $row->tenant_id,
            'passenger_id'     => (int) $row->passenger_id,
            'status'           => $row->status,
            'requested_channel'=> $row->requested_channel,
            'passenger_name'   => $row->passenger_name,
            'passenger_phone'  => $row->passenger_phone,

            'origin' => [
                'label' => $row->origin_label,
                'lat'   => $row->origin_lat !== null ? (float) $row->origin_lat : null,
                'lng'   => $row->origin_lng !== null ? (float) $row->origin_lng : null,
            ],

            'destination' => [
                'label' => $row->dest_label,
                'lat'   => $row->dest_lat !== null ? (float) $row->dest_lat : null,
                'lng'   => $row->dest_lng !== null ? (float) $row->dest_lng : null,
            ],
            'payment_method'  => $row->payment_method ?: 'cash',
            'distance_m'      => $row->distance_m      !== null ? (int)   $row->distance_m      : null,
            'duration_s'      => $row->duration_s      !== null ? (int)   $row->duration_s      : null,
            'quoted_amount'   => $row->quoted_amount   !== null ? (float) $row->quoted_amount   : null,
            'passenger_offer' => $row->passenger_offer !== null ? (float) $row->passenger_offer : null,
            'driver_offer'    => $row->driver_offer    !== null ? (float) $row->driver_offer    : null,
            'agreed_amount'   => $row->agreed_amount   !== null ? (float) $row->agreed_amount   : null,
            'total_amount'    => $row->total_amount    !== null ? (float) $row->total_amount    : null,

              'passenger_onway_at'    => $row->passenger_onway_at,
                'passenger_onboard_at'  => $row->passenger_onboard_at,
                'passenger_finished_at' => $row->passenger_finished_at,

                'cancel_reason' => $row->cancel_reason ? (string) $row->cancel_reason : null,
        'canceled_by'   => $row->canceled_by   ? (string) $row->canceled_by   : null,

            'driver_id'  => $row->driver_id !== null ? (int) $row->driver_id : null,
            'has_driver' => !empty($row->driver_id),

            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'search_expires_at' => $searchExpiresAt,

        ];

        return response()->json([
            'ok'   => true,
            'ride' => $ridePayload,
        ]);
    }

    public function currentAny(Request $req)
    {
        $v = $req->validate([
            'firebase_uid' => 'required|string',
        ]);

        // 1) Resolver pasajero por firebase_uid
        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
            ], 422);
        }

        // 2) Estados que consideramos "ride activo"
        $activeStatuses = [
            'requested', 'searching', 'bidding', 'pending',
            'accepted', 'assigned', 'en_route',
            'arrived', 'on_board',
        ];

        // 3) Buscar el ride activo más reciente del pasajero (en cualquier tenant)
        $row = DB::table('rides')
            ->where('passenger_id', $passenger->id)
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('id')
            ->select([
                'id',
                'tenant_id',
                'passenger_id',
                'status',
                'requested_channel',
                'passenger_name',
                'passenger_phone',
                'origin_label',
                'origin_lat',
                'origin_lng',
                'dest_label',
                'dest_lat',
                'dest_lng',
                'distance_m',
                'duration_s',
                'quoted_amount',
                'passenger_offer',
                'driver_offer',
                'agreed_amount',
                'total_amount',
                'payment_method',
                'driver_id',
                'passenger_onway_at',
                'passenger_onboard_at',
                'passenger_finished_at',
                'cancel_reason',
                 'canceled_by',
                'created_at',
                'updated_at',
            ])
            ->first();

           

        // 4) Si no hay → ok:true, ride:null
        if (! $row) {
            return response()->json([
                'ok'   => true,
                'ride' => null,
            ]);
        }


         $tenantId = (int)$row->tenant_id;
            $searchExpiresAt = $this->computeRideSearchExpiresAt($tenantId, (int)$row->id);



        // 5) Armar payload parecido al de current()
        $ridePayload = [
            'id'               => (int) $row->id,
            'tenant_id'        => (int) $row->tenant_id,
            'passenger_id'     => (int) $row->passenger_id,
            'status'           => $row->status,
            'requested_channel'=> $row->requested_channel,
            'passenger_name'   => $row->passenger_name,
            'passenger_phone'  => $row->passenger_phone,
            'origin' => [
                'label' => $row->origin_label,
                'lat'   => $row->origin_lat !== null ? (float) $row->origin_lat : null,
                'lng'   => $row->origin_lng !== null ? (float) $row->origin_lng : null,
            ],
            'destination' => [
                'label' => $row->dest_label,
                'lat'   => $row->dest_lat !== null ? (float) $row->dest_lat : null,
                'lng'   => $row->dest_lng !== null ? (float) $row->dest_lng : null,
            ],
            'payment_method'  => $row->payment_method ?: 'cash',
            'distance_m'      => $row->distance_m      !== null ? (int)   $row->distance_m      : null,
            'duration_s'      => $row->duration_s      !== null ? (int)   $row->duration_s      : null,
            'quoted_amount'   => $row->quoted_amount   !== null ? (float) $row->quoted_amount   : null,
            'passenger_offer' => $row->passenger_offer !== null ? (float) $row->passenger_offer : null,
            'driver_offer'    => $row->driver_offer    !== null ? (float) $row->driver_offer    : null,
            'agreed_amount'   => $row->agreed_amount   !== null ? (float) $row->agreed_amount   : null,
            'total_amount'    => $row->total_amount    !== null ? (float) $row->total_amount    : null,
            'driver_id'       => $row->driver_id !== null ? (int) $row->driver_id : null,
            'has_driver'      => !empty($row->driver_id),

            'passenger_onway_at'    => $row->passenger_onway_at,
            'passenger_onboard_at'  => $row->passenger_onboard_at,
            'passenger_finished_at' => $row->passenger_finished_at,
            'cancel_reason' => $row->cancel_reason ? (string) $row->cancel_reason : null,
            'canceled_by'   => $row->canceled_by   ? (string) $row->canceled_by   : null,


            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
            'search_expires_at' => $searchExpiresAt,
        ];

        return response()->json([
            'ok'   => true,
            'ride' => $ridePayload,
        ]);
    }

 public function offers(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string',
        ]);

        $tenantId = (int) $v['tenant_id'];

        // 1) Validar pasajero por firebase_uid
        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado',
            ], 422);
        }

        // 2) Ride
        $r = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first();

        if (! $r) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Ride no encontrado',
            ], 404);
        }

        // que el ride sea de este pasajero
        if ((int) $r->passenger_id !== (int) $passenger->id) {
            return response()->json([
                'ok'  => false,
                'msg' => 'No autorizado',
            ], 403);
        }

        // 3) Ofertas asociadas + driver + vehículo asignado actual
        $offers = DB::table('ride_offers as o')
            ->leftJoin('drivers as d', function ($q) use ($tenantId) {
                $q->on('d.id', '=', 'o.driver_id')
                  ->where('d.tenant_id', '=', $tenantId);
            })
            ->leftJoin('driver_vehicle_assignments as a', function ($q) use ($tenantId) {
                $q->on('a.driver_id', '=', 'o.driver_id')
                  ->where('a.tenant_id', '=', $tenantId)
                  ->whereNull('a.end_at'); // asignación vigente
            })
            ->leftJoin('vehicles as v', function ($q) use ($tenantId) {
                $q->on('v.id', '=', 'a.vehicle_id')
                  ->where('v.tenant_id', '=', $tenantId);
            })
            ->where('o.tenant_id', $tenantId)
            ->where('o.ride_id', $ride)
            ->orderByDesc('o.id')
            ->select([
                'o.id          as offer_id',
                'o.status',
                'o.driver_id',
                'o.driver_offer',
                'o.expires_at',
                'o.sent_at',
                'o.responded_at',
                'o.distance_m',
                'o.eta_seconds',

                DB::raw('CEIL(o.eta_seconds / 60) as eta_minutes'),

                // Campos del driver
                'd.name       as driver_name',
                'd.foto_path  as driver_foto_path',

                // Campos básicos del vehículo asignado
                'v.brand      as vehicle_brand',
                'v.model      as vehicle_model',
                'v.plate      as vehicle_plate',
                'v.economico  as vehicle_economico',
            ])
            ->get()
            ->map(function ($o) {
                // casteos básicos
                $o->driver_offer = $o->driver_offer !== null ? (float) $o->driver_offer : null;
                $o->distance_m   = $o->distance_m   !== null ? (int)   $o->distance_m   : null;
                $o->eta_seconds  = $o->eta_seconds  !== null ? (int)   $o->eta_seconds  : null;
                $o->eta_minutes  = $o->eta_minutes  !== null ? (int)   $o->eta_minutes  : null;

                // URL pública del avatar (a partir de foto_path)
                if (!empty($o->driver_foto_path)) {
                    $o->avatar_url = Storage::disk('public')->url($o->driver_foto_path);
                } else {
                    $o->avatar_url = null;
                }

                // Opcional: etiqueta compacta de vehículo (marca + modelo)
                $brand = $o->vehicle_brand ?? '';
                $model = $o->vehicle_model ?? '';
                $label = trim($brand.' '.$model);
                $o->vehicle_label = $label !== '' ? $label : null;

                // si no quieres exponer foto_path crudo:
                unset($o->driver_foto_path);

                return $o;
            });

        return response()->json([
            'ok'    => true,
            'ride'  => [
                'id'              => (int) $r->id,
                'status'          => $r->status,
                'passenger_offer' => $r->passenger_offer !== null ? (float) $r->passenger_offer : null,
                'quoted_amount'   => $r->quoted_amount   !== null ? (float) $r->quoted_amount   : null,
                'driver_offer'    => $r->driver_offer    !== null ? (float) $r->driver_offer    : null,
                'agreed_amount'   => $r->agreed_amount   !== null ? (float) $r->agreed_amount   : null,
            ],
            'items' => $offers,
        ]);
    }

      public function acceptOffer(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'offer_id'     => 'required|integer',
            'firebase_uid' => 'required|string',
        ]);

        $tenantId = (int) $v['tenant_id'];

        // validar pasajero
        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json(['ok' => false, 'msg' => 'Pasajero no encontrado'], 422);
        }

        DB::beginTransaction();
        try {
            // Ride con lock
            $r = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->lockForUpdate()
                ->first();

            if (! $r) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
            }

            if ((int)$r->passenger_id !== (int)$passenger->id) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
            }

            $status = strtolower($r->status ?? '');
            if (in_array($status, ['finished', 'canceled'], true)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride ya no es asignable'], 409);
            }

            // si ya tiene driver distinto, no permitir
            if (!is_null($r->driver_id) && (int)$r->driver_id !== 0) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride ya tiene conductor asignado'], 409);
            }

            // Offer con lock
            $o = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $v['offer_id'])
                ->lockForUpdate()
                ->first();

            if (! $o || (int)$o->ride_id !== (int)$ride) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta no válida'], 404);
            }

            $offerStatus = strtolower($o->status ?? '');
            if (!in_array($offerStatus, ['offered', 'pending_passenger'], true)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta ya no disponible'], 409);
            }

            // validar expiración de la oferta
            if (!empty($o->expires_at)) {
                $expiresAt = now()->parse($o->expires_at);
                if (now()->greaterThan($expiresAt)) {
                    DB::table('ride_offers')
                        ->where('id', $o->id)
                        ->update([
                            'status'       => 'expired',
                            'responded_at' => now(),
                            'updated_at'   => now(),
                        ]);

                    DB::rollBack();
                    return response()->json(['ok' => false, 'msg' => 'Oferta expirada'], 409);
                }
            }

            // monto acordado: driver_offer si existe, sino passenger_offer, sino quoted_amount
            $agreed = $o->driver_offer ?? $r->passenger_offer ?? $r->quoted_amount;
            $agreed = (float) ($agreed ?? 0);
            if ($agreed <= 0) {
                $agreed = (float) max(10, $r->quoted_amount ?? 0);
            }

            // actualizar ride
            DB::table('rides')
                ->where('tenant_id', $tenantId)->where('id', $ride)
                ->update([
                    'driver_id'     => $o->driver_id,
                    'status'        => 'accepted',
                    'accepted_at'   => now(),
                    'agreed_amount' => $agreed,
                    'total_amount'  => $agreed,
                    'updated_at'    => now(),
                ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => $status ?: null,
                'new_status'  => 'accepted',
                'meta'        => json_encode([
                    'accepted_by'   => 'passenger',
                    'offer_id'      => $o->id,
                    'driver_id'     => $o->driver_id,
                    'agreed_amount' => $agreed,
                ]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // marcar oferta aceptada
            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $o->id)
                ->update([
                    'status'       => 'accepted',
                    
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            // liberar las demás (offered + pending_passenger)
            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->where('id', '!=', $o->id)
                ->whereIn('status', ['offered', 'pending_passenger'])
                ->update([
                    'status'       => 'released',
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }

        // Broadcast: al driver y al ride
        OfferBroadcaster::emitStatus(
            $tenantId,
            (int)$o->driver_id,
            (int)$ride,
            (int)$o->id,
            'accepted'
        );

        RideBroadcaster::bidResult(
            tenantId:     $tenantId,
            rideId:       (int)$ride,
            offerId:      (int)$o->id,
            result:       'accepted',
            agreedAmount: (int)round($agreed)
        );

        RideBroadcaster::afterAccept(
            tenantId:     $tenantId,
            rideId:       (int)$ride,
            driverId:     (int)$o->driver_id,
            offerId:      (int)$o->id,
            agreedAmount: $agreed
        );

        return response()->json([
            'ok'            => true,
            'ride_id'       => (int)$ride,
            'offer_id'      => (int)$o->id,
            'driver_id'     => (int)$o->driver_id,
            'agreed_amount' => $agreed,
            'status'        => 'accepted',
        ]);
    }


    public function rejectOffer(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'offer_id'     => 'required|integer',
            'firebase_uid' => 'required|string',
        ]);

        $tenantId = (int) $v['tenant_id'];

        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json(['ok' => false, 'msg' => 'Pasajero no encontrado'], 422);
        }

        DB::beginTransaction();
        try {
            $r = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->lockForUpdate()
                ->first();

            if (! $r) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
            }

            if ((int)$r->passenger_id !== (int)$passenger->id) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
            }

            $o = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $v['offer_id'])
                ->lockForUpdate()
                ->first();

            if (! $o || (int)$o->ride_id !== (int)$ride) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta no válida'], 404);
            }

            $offerStatus = strtolower($o->status ?? '');
            if (!in_array($offerStatus, ['offered', 'pending_passenger'], true)) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta ya no disponible'], 409);
            }

            if (!empty($o->expires_at)) {
                $expiresAt = now()->parse($o->expires_at);
                if (now()->greaterThan($expiresAt)) {
                    DB::table('ride_offers')
                        ->where('id', $o->id)
                        ->update([
                            'status'       => 'expired',
                            'responded_at' => now(),
                            'updated_at'   => now(),
                        ]);
                    DB::rollBack();
                    return response()->json(['ok' => false, 'msg' => 'Oferta expirada'], 409);
                }
            }

            DB::table('ride_offers')
                ->where('id', $o->id)
                ->update([
                    'status'       => 'rejected',
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => strtolower($r->status ?? null),
                'new_status'  => 'bidding_rejected',
                'meta'        => json_encode([
                    'by'        => 'passenger',
                    'offer_id'  => $o->id,
                    'driver_id' => $o->driver_id,
                ]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }

        // este driver ya gastó su cartucho
        OfferBroadcaster::emitStatus(
            $tenantId,
            (int)$o->driver_id,
            (int)$ride,
            (int)$o->id,
            'rejected'
        );

        RideBroadcaster::bidResult(
            tenantId:     $tenantId,
            rideId:       (int)$ride,
            offerId:      (int)$o->id,
            result:       'rejected',
            agreedAmount: null
        );

        return response()->json([
            'ok'       => true,
            'ride_id'  => (int)$ride,
            'offer_id' => (int)$o->id,
            'status'   => 'rejected',
        ]);
    }


    public function cancel(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string',
            'reason'       => 'nullable|string|max:160',
        ]);

        $tenantId = (int) $v['tenant_id'];

        // 1) Verificar pasajero por firebase_uid
        $passenger = Passenger::where('firebase_uid', $v->firebase_uid ?? $v['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json(['ok' => false, 'msg' => 'Pasajero no encontrado'], 422);
        }

        return DB::transaction(function () use ($tenantId, $ride, $v, $passenger) {

            // 2) Lock al ride
            $row = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
            }

            // 3) Verificar que el ride sí sea de este pasajero
            if ((int) $row->passenger_id !== (int) $passenger->id) {
                return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
            }

            $status = strtolower($row->status ?? '');

            // Idempotente si ya está terminado/cancelado
            if (in_array($status, ['finished', 'canceled'], true)) {
                return response()->json(['ok' => true]);
            }

            $cancelReason = $v['reason'] ?? null;

            // 4) Marcar ride como cancelado por pasajero
            DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->update([
                    'status'        => 'canceled',
                    'canceled_at'   => now(),
                    'cancel_reason' => $cancelReason,
                    'canceled_by'   => 'passenger',
                    'updated_at'    => now(),
                ]);

            // Liberar al driver actual, si lo hay
            if (!empty($row->driver_id)) {
                DB::table('drivers')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $row->driver_id)
                    ->update([
                        'status'     => 'idle',
                        'updated_at' => now(),
                    ]);
            }

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => $status ?: null,
                'new_status'  => 'canceled',
                'meta'        => json_encode([
                    'reason' => $cancelReason,
                    'by'     => 'passenger_app',
                ]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // 5) Cerrar ofertas (offered/pending -> released, accepted -> canceled)
            $offeredAndPending = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->whereIn('status', ['offered', 'pending_passenger'])
                ->get(['id','tenant_id','driver_id','ride_id']);

            $accepted = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->where('status', 'accepted')
                ->get(['id','tenant_id','driver_id','ride_id']);

            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->whereIn('status', ['offered', 'pending_passenger'])
                ->update([
                    'status'       => 'released',
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->where('status', 'accepted')
                ->update([
                    'status'       => 'canceled',
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);

            // Emitir a los drivers afectados
            foreach ($offeredAndPending as $o) {
                OfferBroadcaster::emitStatus(
                    (int)$o->tenant_id,
                    (int)$o->driver_id,
                    (int)$o->ride_id,
                    (int)$o->id,
                    'released'
                );
            }
            foreach ($accepted as $o) {
                OfferBroadcaster::emitStatus(
                    (int)$o->tenant_id,
                    (int)$o->driver_id,
                    (int)$o->ride_id,
                    (int)$o->id,
                    'canceled'
                );
            }

            // 6) Eventos de cancelación (ride completo + realtime driver)
            try {
                RideBroadcaster::canceled(
                    $tenantId,
                    (int)$ride,
                    'passenger',
                    $cancelReason
                );

                if (!empty($row->driver_id)) {
                    \App\Services\Realtime::toDriver($tenantId, (int)$row->driver_id)
                        ->emit('ride.update', [
                            'ride_id'       => (int)$ride,
                            'status'        => 'canceled',
                            'cancel_reason' => $cancelReason,
                            'canceled_by'   => 'passenger',
                            'canceled_at'   => now()->format('Y-m-d H:i:s'),
                        ]);

                    \App\Services\Realtime::toDriver($tenantId, (int)$row->driver_id)
                        ->emit('ride.canceled', [
                            'ride_id' => (int)$ride,
                            'reason'  => $cancelReason,
                            'by'      => 'passenger',
                        ]);
                }
            } catch (\Throwable $eventError) {
                \Log::error('Passenger cancel events failed', [
                    'ride'  => $ride,
                    'error' => $eventError->getMessage(),
                ]);
            }

            return response()->json(['ok' => true]);
        });
    }


private function computeRideSearchExpiresAt(int $tenantId, int $rideId): ?string
{
    // Tomamos el MAX(expires_at) de la ola actual (o de todas las ofertas del ride)
    // Si no hay ofertas, devolvemos null.
    $max = DB::table('ride_offers')
        ->where('tenant_id', $tenantId)
        ->where('ride_id', $rideId)
        ->max('expires_at');

    return $max ? (string) $max : null;
}
   


  public function onTheWay(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $tenantId = (int) $v['tenant_id'];

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first();

        if (! $row) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Ride no encontrado',
            ], 404);
        }

        $now = now();

        // Solo registramos la primera vez para no ensuciar history
        if (empty($row->passenger_onway_at)) {
            DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->update([
                    'passenger_onway_at' => $now,
                    'updated_at'         => $now,
                ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => strtolower($row->status ?? null),
                'new_status'  => 'passenger_on_way',
                'meta'        => json_encode([
                    'by' => 'passenger_app',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Señal en tiempo real (no tocamos el status canónico)
        RideBroadcaster::passengerOnWay($tenantId, $ride);

        return response()->json(['ok' => true]);
    }


       public function onBoard(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $tenantId = (int) $v['tenant_id'];

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first();

        if (! $row) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Ride no encontrado',
            ], 404);
        }

        $now = now();

        if (empty($row->passenger_onboard_at)) {
            DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->update([
                    'passenger_onboard_at' => $now,
                    'updated_at'           => $now,
                ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => strtolower($row->status ?? null),
                'new_status'  => 'passenger_on_board',
                'meta'        => json_encode([
                    'by' => 'passenger_app',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        RideBroadcaster::passengerOnBoard($tenantId, $ride);

        return response()->json(['ok' => true]);
    }


        /**
         * POST /api/passenger/rides/{ride}/finished
         * El pasajero indica "Ya llegué". Es una señal para el driver / sistema.
         * Aquí tampoco cerramos el ride: sigue siendo el driver quien llama a finish().
         */
      /**
     * POST /api/passenger/rides/{ride}/finished
     * El pasajero indica "Ya llegué".
     * No cerramos el ride aquí, sólo registramos la señal y emitimos evento.
     */
    public function finishByPassenger(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $tenantId = (int) $v['tenant_id'];

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first();

        if (! $row) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Ride no encontrado',
            ], 404);
        }

        $status = strtolower($row->status ?? '');
        $now    = now();

        // Si ya está totalmente cerrado, solo mandamos señal en tiempo real
        if (! in_array($status, ['finished', 'canceled'], true)
            && empty($row->passenger_finished_at)) {

            DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $ride)
                ->update([
                    'passenger_finished_at' => $now,
                    'updated_at'            => $now,
                ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $ride,
                'prev_status' => $status ?: null,
                'new_status'  => 'passenger_finished',
                'meta'        => json_encode([
                    'by' => 'passenger_app',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        RideBroadcaster::passengerFinished($tenantId, $ride);

        return response()->json(['ok' => true]);
    }



     public function history(Request $request)
    {
        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|min:1|max:50',
            'status'       => 'nullable|string|in:finished,canceled,all',
        ]);

        $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();

        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado.',
            ], 404);
        }

        $perPage = $data['per_page'] ?? 20;
        $status  = $data['status'] ?? 'finished';

        $q = Ride::query()
            ->where('passenger_id', $passenger->id)
            ->orderByDesc('created_at');

        // Por defecto solo mostramos viajes terminados
        if ($status === 'finished') {
            $q->whereIn('status', ['finished', 'completed', 'paid']);
        } elseif ($status === 'canceled') {
            $q->whereIn('status', ['canceled', 'cancelled_by_passenger', 'cancelled_by_driver']);
        }

        $paginator  = $q->paginate($perPage);
        $collection = $paginator->getCollection();

        // --------- Ratings: driver → passenger por ride ---------
        $rideIds = $collection->pluck('id')->all();

        $ratingsByRide = collect();
        if (!empty($rideIds)) {
            $ratingsByRide = Rating::query()
                ->whereIn('ride_id', $rideIds)
                ->where('rater_type', 'driver')
                ->where('rated_type', 'passenger')
                ->get()
                ->keyBy('ride_id');
        }

        $items = $collection->map(function (Ride $ride) use ($ratingsByRide) {
            // ------ Driver / vehículo (opcional) ------
            $driverName       = null;
            $driverAvatarUrl  = null;
            $vehiclePlate     = null;
            $vehicleEconomico = null;
            $vehicleLabel     = null;

            if (!empty($ride->driver_id)) {
                // Driver básico
                $driver = DB::table('drivers')
                    ->where('tenant_id', $ride->tenant_id)
                    ->where('id', $ride->driver_id)
                    ->first(['id', 'name', 'foto_path']);

                if ($driver) {
                    $driverName = $driver->name ?? null;

                    if (!empty($driver->foto_path)) {
                        $driverAvatarUrl = Storage::disk('public')->url($driver->foto_path);
                    }

                    // Vehículo vigente (misma lógica que en offers())
                    $assignment = DB::table('driver_vehicle_assignments as a')
                        ->leftJoin('vehicles as v', function ($q) use ($ride) {
                            $q->on('v.id', '=', 'a.vehicle_id')
                              ->where('v.tenant_id', '=', $ride->tenant_id);
                        })
                        ->where('a.tenant_id', $ride->tenant_id)
                        ->where('a.driver_id', $ride->driver_id)
                        ->whereNull('a.end_at')
                        ->orderByDesc('a.id')
                        ->first([
                            'v.brand      as vehicle_brand',
                            'v.model      as vehicle_model',
                            'v.plate      as vehicle_plate',
                            'v.economico  as vehicle_economico',
                        ]);

                    if ($assignment) {
                        $vehiclePlate     = $assignment->vehicle_plate ?? null;
                        $vehicleEconomico = $assignment->vehicle_economico ?? null;

                        $label = trim(($assignment->vehicle_brand ?? '') . ' ' . ($assignment->vehicle_model ?? ''));
                        $vehicleLabel = $label !== '' ? $label : null;
                    }
                }
            }

            // ------ Monto “real” del viaje ------
            $amountRaw = $ride->final_amount
                ?? $ride->total_amount
                ?? $ride->agreed_amount
                ?? $ride->passenger_offer
                ?? $ride->quoted_amount
                ?? 0;

            $amount = (int) round($amountRaw);

            // ------ Rating driver → passenger (para este ride) ------
            $ratingFromDriver = null;
            if ($ratingsByRide->has($ride->id)) {
                $ratingRow       = $ratingsByRide->get($ride->id);
                $ratingFromDriver = $ratingRow ? (int) $ratingRow->rating : null;
            }

            return [
                'id'            => (int) $ride->id,
                'tenant_id'     => (int) $ride->tenant_id,
                'status'        => $ride->status,
                'status_label'  => ucfirst($ride->status ?? ''),

                'created_at'    => optional($ride->created_at)->toIso8601String(),
                'finished_at'   => optional($ride->finished_at)->toIso8601String(),

                // Direcciones legibles
                'origin'        => $ride->origin_label ?? null,
                'destination'   => $ride->dest_label   ?? null,

                // Coordenadas
                'origin_lat'    => $ride->origin_lat !== null ? (float) $ride->origin_lat : null,
                'origin_lng'    => $ride->origin_lng !== null ? (float) $ride->origin_lng : null,
                'dest_lat'      => $ride->dest_lat   !== null ? (float) $ride->dest_lat   : null,
                'dest_lng'      => $ride->dest_lng   !== null ? (float) $ride->dest_lng   : null,

                'distance_m'    => $ride->distance_m !== null ? (int) $ride->distance_m : null,
                'duration_s'    => $ride->duration_s !== null ? (int) $ride->duration_s : null,

                'amount'        => $amount,
                'currency'      => $ride->currency ?? 'MXN',
                'payment_method'=> $ride->payment_method ?? 'cash',

                // Info del driver / vehículo
                'driver_id'         => $ride->driver_id !== null ? (int) $ride->driver_id : null,
                'driver_name'       => $driverName,
                'driver_avatar_url' => $driverAvatarUrl,

                'vehicle_plate'     => $vehiclePlate,
                'vehicle_economico' => $vehicleEconomico,
                'vehicle_label'     => $vehicleLabel,

                // ⭐ Rating que el conductor le dio a este pasajero en ESTE ride
                'rating_from_driver'=> $ratingFromDriver,
            ];
        })->values();

        return response()->json([
            'ok'   => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ]);
    }


}
