<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Passenger;
use App\Services\TenantResolverService;
use App\Services\FareQuoteService;
use App\Services\CreateRideService;
use App\Services\QuoteRecalcService;
use App\Services\RideBroadcaster;
use Illuminate\Http\Request;
use App\Services\OfferBroadcaster; // si no lo tienes ya
use App\Services\Realtime; 
use Illuminate\Support\Facades\DB;

class PassengerRideController extends Controller
{
    /**
     * /api/passenger/quote
     */
    public function quote(
        Request $req,
        TenantResolverService $tenantResolver,
        FareQuoteService $fareQuote,
    ) {
        $v = $req->validate([
            'origin_lat'    => 'required|numeric',
            'origin_lng'    => 'required|numeric',
            'dest_lat'      => 'required|numeric',
            'dest_lng'      => 'required|numeric',
            'round_to_step' => 'nullable|numeric',

            'stops'         => 'nullable|array|max:2',
            'stops.*.lat'   => 'required_with:stops|numeric',
            'stops.*.lng'   => 'required_with:stops|numeric',

            // opcional, solo para localizar pasajero
            'firebase_uid'  => 'nullable|string|max:128',
        ]);

        // 1) Resolver tenant según pickup
        $tenant = $tenantResolver->resolveForPickupPoint(
            (float) $v['origin_lat'],
            (float) $v['origin_lng'],
        );

        if (! $tenant) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Fuera de zona de cobertura',
            ], 422);
        }

        // 2) Buscar pasajero (solo info, todavía no se crea ride)
        $passenger = null;
        if (!empty($v['firebase_uid'])) {
            $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        }

        $origin = [
            'lat' => (float) $v['origin_lat'],
            'lng' => (float) $v['origin_lng'],
        ];
        $destination = [
            'lat' => (float) $v['dest_lat'],
            'lng' => (float) $v['dest_lng'],
        ];

        $stops = [];
        if (!empty($v['stops'])) {
            foreach ($v['stops'] as $s) {
                $stops[] = [
                    'lat' => (float) $s['lat'],
                    'lng' => (float) $s['lng'],
                ];
            }
        }

        $roundToStep = $req->has('round_to_step')
            ? (float) $req->input('round_to_step')
            : null;

        // 3) Calcular tarifa usando tus reglas de FareQuoteService
        $res = $fareQuote->quoteForTenantAndPoints(
            tenantId:    (int) $tenant->id,
            origin:      $origin,
            destination: $destination,
            stops:       $stops,
            roundToStep: $roundToStep,
        );

        // En tu log venía como "amount"
        $amount     = (int) ($res['amount'] ?? 0);
        $distance_m = $res['distance_m'] ?? null;
        $duration_s = $res['duration_s'] ?? null;

        return response()->json([
            'ok'            => true,
            'tenant_id'     => (int) $tenant->id,
            'passenger_id'  => $passenger?->id,
            'amount'        => $amount,
            'distance_m'    => $distance_m,
            'duration_s'    => $duration_s,
            'stops_n'       => count($stops),
        ]);
    }

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
    ) {
        $v = $req->validate([
            'tenant_id'      => 'required|integer|exists:tenants,id',

            'pickup_lat'     => 'required|numeric',
            'pickup_lng'     => 'required|numeric',
            'pickup_address' => 'nullable|string|max:160',

            'dest_lat'       => 'required|numeric',
            'dest_lng'       => 'required|numeric',
            'dest_address'   => 'nullable|string|max:160',

            'passengers'     => 'required|integer|min:1|max:6',

            // quick notes del pasajero (las juntamos a un solo string)
            'notes'          => 'nullable|array|max:5',
            'notes.*'        => 'string|max:80',

            // paradas intermedias (máx 2)
            'stops'          => 'nullable|array|max:2',
            'stops.*.lat'    => 'required_with:stops|numeric',
            'stops.*.lng'    => 'required_with:stops|numeric',
            'stops.*.label'  => 'nullable|string|max:160',

            // tarifa que eligió en el slider
            'offered_fare'   => 'required|integer|min:10',

            // identidad del pasajero
            'firebase_uid'   => 'required|string|max:128',
        ]);

        /** @var \App\Models\Tenant $tenant */
        $tenant = Tenant::findOrFail($v['tenant_id']);
        $tenantId = (int) $tenant->id;

        // 1) Validar que el pickup SÍ pertenece al tenant (anti-trampa)
        $coverageTenant = $tenantResolver->resolveForPickupPoint(
            (float) $v['pickup_lat'],
            (float) $v['pickup_lng'],
        );

        if (! $coverageTenant || (int)$coverageTenant->id !== $tenantId) {
            return response()->json([
                'ok'  => false,
                'msg' => 'La ubicación de recogida no coincide con la zona del operador.',
            ], 422);
        }

        // 2) Buscar pasajero por firebase_uid (NO guardamos el uid en rides)
        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();

        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
            ], 422);
        }

        // 3) Preparar origen/destino y stops para recalcular quote
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

        // 4) Recalcular quote (misma lógica que /dispatch/quote)
        $quote = $fareQuote->quoteForTenantAndPoints(
            tenantId:    $tenantId,
            origin:      $origin,
            destination: $destination,
            stops:       $stopsForQuote,
            roundToStep: null,
        );

        $recommended = (int) ($quote['amount'] ?? 0);
        if ($recommended < 10) {
            // por sanidad, que nunca quede en 0
            $recommended = 10;
        }

        $distance_m  = isset($quote['distance_m']) ? (int)$quote['distance_m'] : null;
        $duration_s  = isset($quote['duration_s']) ? (int)$quote['duration_s'] : null;
        $route_poly  = $quote['route_polyline'] ?? null;

        // 5) Aplicar regla de rango (-10% / +15%) al fare del pasajero
        $minAllowed = (int) floor($recommended * 0.90);
        $maxAllowed = (int) ceil($recommended * 1.15);

        $offered = (int) $v['offered_fare'];
        if ($offered < $minAllowed) {
            $offered = $minAllowed;
        } elseif ($offered > $maxAllowed) {
            $offered = $maxAllowed;
        }

        // 6) Convertir notes[] → string como espera RideController::store
        $notesText = null;
        if (!empty($v['notes'])) {
            // ejemplo simple: "nota1 | nota2"
            $notesText = implode(' | ', $v['notes']);
        }

        // 7) Armar $data EXACTO al formato de CreateRideService
        $data = [
            'passenger_name'  => $passenger->name,
            'passenger_phone' => $passenger->phone,

            'origin_label' => $v['pickup_address'] ?? null,
            'origin_lat'   => $v['pickup_lat'],
            'origin_lng'   => $v['pickup_lng'],

            'dest_label' => $v['dest_address'] ?? null,
            'dest_lat'   => $v['dest_lat'],
            'dest_lng'   => $v['dest_lng'],

            // stops los manejamos igual que RideController::store (después del create)
            'stops' => $stopsIn,

            'payment_method'   => 'cash',  // por ahora
            'fare_mode'        => 'fixed', // porque ya traes quoted_amount
            'notes'            => $notesText,
            'pax'              => $v['passengers'],
            'scheduled_for'    => null,

            'quoted_amount'     => $recommended,
            'distance_m'        => $distance_m,
            'duration_s'        => $duration_s,
            'route_polyline'    => $route_poly,
            'requested_channel' => 'passenger_app',
        ];

        DB::beginTransaction();
        try {
            // 8) Crear ride con la MISMA lógica que el panel
            $ride = $createRide->create($data, $tenantId);

            // 9) Guardar stops_json + recalc (copiado de RideController::store)
            $stops = $data['stops'] ?? [];
            if (!empty($stops)) {
                $stops = array_values(array_slice(array_map(function ($s) {
                    return [
                        'lat'   => isset($s['lat'])   ? (float)$s['lat']   : null,
                        'lng'   => isset($s['lng'])   ? (float)$s['lng']   : null,
                        'label' => isset($s['label']) ? (trim((string)$s['label']) ?: null) : null,
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

            // 10) Actualizar campos específicos de marketplace/passenger
            DB::table('rides')
                ->where('tenant_id', $tenantId)->where('id', $ride->id)
                ->update([
                    'passenger_id'    => $passenger->id,
                    'allow_bidding'   => 1,
                    'passenger_offer' => $offered,
                    'agreed_amount'   => $offered,           // de inicio tomamos el del pasajero
                    'requested_channel' => 'passenger_app',  // por si CreateRideService no lo puso
                    'updated_at'      => now(),
                ]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'ok'  => false,
                'msg' => $e->getMessage(),
            ], 500);
        }

        // 11) Emitir evento “buscando conductor” al canal del ride + driver/panel
        RideBroadcaster::requestedFromPassenger(
            tenantId:       $tenantId,
            rideId:         (int) $ride->id,
            recommended:    $recommended,
            passengerOffer: $offered,
            distanceM:      $distance_m,
            durationS:      $duration_s,
        );

        return response()->json([
            'ok'              => true,
            'ride_id'         => (int) $ride->id,
            'tenant_id'       => $tenantId,
            'passenger_id'    => (int) $passenger->id,
            'status'          => $ride->status,
            'recommended'     => $recommended,
            'passenger_offer' => $offered,
            'distance_m'      => $distance_m,
            'duration_s'      => $duration_s,
        ], 201);
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
            // ride
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
            if (in_array($status, ['finished', 'canceled'])) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Ride ya no es asignable'], 409);
            }

            // offer
            $o = DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('id', $v['offer_id'])
                ->lockForUpdate()
                ->first();

            if (! $o || (int)$o->ride_id !== (int)$ride) {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta no válida'], 404);
            }

            if (strtolower($o->status) !== 'offered') {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta ya no disponible'], 409);
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
                    'accepted_by' => 'passenger',
                    'offer_id'    => $o->id,
                    'driver_id'   => $o->driver_id,
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

            // liberar las demás (si hubieran)
            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)
                ->where('ride_id', $ride)
                ->where('id', '!=', $o->id)
                ->where('status', 'offered')
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
            tenantId: $tenantId,
            rideId:   (int)$ride,
            offerId:  (int)$o->id,
            result:   'accepted',
            agreedAmount: (int)round($agreed)
        );

        RideBroadcaster::afterAccept(
            tenantId:   $tenantId,
            rideId:     (int)$ride,
            driverId:   (int)$o->driver_id,
            offerId:    (int)$o->id,
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

       /** POST /api/passenger/rides/{ride}/reject-offer */
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

            if (strtolower($o->status) !== 'offered') {
                DB::rollBack();
                return response()->json(['ok' => false, 'msg' => 'Oferta ya no disponible'], 409);
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
                    'by'       => 'passenger',
                    'offer_id' => $o->id,
                    'driver_id'=> $o->driver_id,
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
            tenantId: $tenantId,
            rideId:   (int)$ride,
            offerId:  (int)$o->id,
            result:   'rejected',
            agreedAmount: null
        );

        return response()->json([
            'ok'        => true,
            'ride_id'   => (int)$ride,
            'offer_id'  => (int)$o->id,
            'status'    => 'rejected',
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
    $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
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

        // 4) Marcar ride como cancelado por pasajero
        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->update([
                'status'        => 'canceled',
                'canceled_at'   => now(),
                'cancel_reason' => $v['reason'] ?? null,
                'canceled_by'   => 'passenger',
                'updated_at'    => now(),
            ]);

        DB::table('ride_status_history')->insert([
            'tenant_id'   => $tenantId,
            'ride_id'     => $ride,
            'prev_status' => $status ?: null,
            'new_status'  => 'canceled',
            'meta'        => json_encode([
                'reason' => $v['reason'] ?? null,
                'by'     => 'passenger_app',
            ]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 5) Cerrar ofertas (igual que en cancelRide de dispatch)
        $offered = DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->where('status', 'offered')
            ->get(['id','tenant_id','driver_id','ride_id']);

        $accepted = DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->where('status', 'accepted')
            ->get(['id','tenant_id','driver_id','ride_id']);

        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->where('status', 'offered')
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
        foreach ($offered as $o) {
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

        // Notificar al driver actual, si lo hay
        if (!empty($row->driver_id)) {
            Realtime::toDriver($tenantId, (int)$row->driver_id)
                ->emit('ride.canceled', [
                    'ride_id' => (int)$ride,
                    'reason'  => $v['reason'] ?? null,
                    'by'      => 'passenger',
                ]);
        }

        return response()->json(['ok' => true]);
    });
}

    public function onTheWay(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $tenantId = (int) $v['tenant_id'];

        // (opcional) validar que el ride le pertenece a este passenger,
        // usando firebase_uid -> passenger_id -> ride.passenger_id

        RideBroadcaster::passengerOnWay($tenantId, $ride);

        return response()->json(['ok' => true]);
    }

}
