<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OfferBroadcaster;
use App\Services\RideBroadcaster;
use App\Services\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\DriverWalletService;
use App\Models\Ride;


class RideController extends Controller
{   
     protected DriverWalletService $walletService;

    public function __construct(DriverWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

   private function tenantIdFrom(Request $req): int
    {
        $user       = $req->user();
        $userTenant = $user->tenant_id ?? null;
        $header     = $req->header('X-Tenant-ID');

        if ($header !== null && $header !== '') {
            $tid = (int) $header;

            if ($userTenant && $userTenant != $tid && empty($user->is_sysadmin)) {
                abort(403, 'Tenant inválido para este usuario');
            }

            return $tid;
        }

        if ($userTenant) {
            return (int) $userTenant;
        }

        abort(403, 'Tenant no determinado');
    }


    /** GET /api/driver/rides/active - RIDE ACTIVO PARA DRIVER */
    public function activeForDriver(Request $req)
    {
        try {
            $user = $req->user();
            $tenantId = $this->tenantIdFrom($req);
            
            // Obtener driver_id del usuario autenticado
            $driverId = DB::table('drivers')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->value('id');
            
            if (!$driverId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Driver no encontrado'
                ], 404);
            }

            \Log::info("Buscando ride activo para driver", [
                'driver_id' => $driverId,
                'tenant_id' => $tenantId,
                'user_id' => $user->id
            ]);

            // Buscar ride activo para este driver
            $ride = DB::table('rides as r')
                ->where('r.tenant_id', $tenantId)
                ->where('r.driver_id', $driverId)
                ->whereIn('r.status', [
                    'accepted', 'dispatch', 'active', 
                    'arrived', 'on_board', 'onboard',
                    'en_route', 'enroute', 'boarding'
                ])
                ->select([
                    'r.id', 'r.tenant_id', 'r.status', 'r.requested_channel',
                    'r.passenger_id', 'r.passenger_name', 'r.passenger_phone',
                    'r.origin_label', 'r.origin_lat', 'r.origin_lng',
                    'r.dest_label', 'r.dest_lat', 'r.dest_lng',
                    'r.fare_mode', 'r.payment_method', 'r.notes', 'r.pax',
                    'r.distance_m', 'r.duration_s', 'r.route_polyline',
                    'r.quoted_amount', 'r.total_amount',
                    'r.passenger_offer', 'r.driver_offer', 'r.agreed_amount',

                    'r.driver_id', 'r.vehicle_id', 'r.sector_id', 'r.stand_id', 'r.shift_id',
                    'r.scheduled_for', 'r.requested_at', 'r.accepted_at', 'r.arrived_at', 'r.onboard_at',
                    'r.finished_at', 'r.canceled_at', 'r.cancel_reason', 'r.canceled_by',
                    'r.created_by', 'r.created_at', 'r.updated_at',
                    'r.stops_json', 'r.stops_count', 'r.stop_index',
                ])
                ->orderByDesc('r.accepted_at')
                ->first();

            \Log::info("Resultado de búsqueda de ride activo", [
                'ride_encontrado' => $ride ? $ride->id : 'null',
                'status' => $ride ? $ride->status : 'null'
            ]);

            if (!$ride) {
                return response()->json([
                    'ok' => true,
                    'ride' => null,
                    'message' => 'No hay ride activo'
                ]);
            }

            // Transformar datos
            $ride->stops = [];
            if (!empty($ride->stops_json)) {
                $tmp = json_decode($ride->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $ride->stops = $tmp;
                }
            }

            // Normalizar tipos de datos
            $ride->origin_lat = isset($ride->origin_lat) ? (float)$ride->origin_lat : null;
            $ride->origin_lng = isset($ride->origin_lng) ? (float)$ride->origin_lng : null;
            $ride->dest_lat   = isset($ride->dest_lat)   ? (float)$ride->dest_lat   : null;
            $ride->dest_lng   = isset($ride->dest_lng)   ? (float)$ride->dest_lng   : null;

            $ride->distance_m = isset($ride->distance_m) ? (int)$ride->distance_m : null;
            $ride->duration_s = isset($ride->duration_s) ? (int)$ride->duration_s : null;

            $ride->quoted_amount = isset($ride->quoted_amount) ? (float)$ride->quoted_amount : null;
            $ride->total_amount  = isset($ride->total_amount)  ? (float)$ride->total_amount  : null;
             $ride->passenger_offer = isset($ride->passenger_offer) ? (float)$ride->passenger_offer : null;
            $ride->agreed_amount   = isset($ride->agreed_amount)   ? (float)$ride->agreed_amount   : null;

            $ride->stops_count = isset($ride->stops_count) ? (int)$ride->stops_count : 0;
            $ride->stop_index  = isset($ride->stop_index)  ? (int)$ride->stop_index  : 0;

            // Limpiar campo stops_json
            unset($ride->stops_json);


              // ---- AVATAR DEL PASAJERO (desde tabla passengers) ----
            $ride->passenger_avatar_url = null;
            $ride->avatar_url           = null; // alias genérico por si el cliente usa este nombre

            if (!empty($ride->passenger_id)) {
                $passenger = DB::table('passengers')
                    ->where('id', $ride->passenger_id)
                    ->first(['avatar_url']);

                if ($passenger && !empty($passenger->avatar_url)) {
                    $ride->passenger_avatar_url = $passenger->avatar_url;
                    $ride->avatar_url           = $passenger->avatar_url;
                }
            }

            // ---- RATING DEL PASAJERO ----
            $ride->passenger_rating       = null;
            $ride->passenger_rating_count = null;

            if (!empty($ride->passenger_id)) {
                $ratingRow = $this->passengerRatingSummary($tenantId, (int)$ride->passenger_id);

                if ($ratingRow && $ratingRow->total_ratings > 0) {
                    $ride->passenger_rating       = (float) $ratingRow->avg_rating;
                    $ride->passenger_rating_count = (int) $ratingRow->total_ratings;
                }
            }
             $ride->amount = $this->resolveFinalAmount($ride);

                         // Si total_amount viene NULL pero ya sabemos el monto final, lo fijamos
            if ($ride->total_amount === null && $ride->amount !== null) {
                DB::table('rides')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $ride->id)
                    ->update([
                        'total_amount' => $ride->amount,
                        'updated_at'   => now(),
                    ]);

                $ride->total_amount = $ride->amount;
            }


            return response()->json([
                'ok' => true,
                'ride' => $ride
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en activeForDriver', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /** GET /api/rides */
    public function index(Request $req)
    {
        $tenantId = $this->tenantIdFrom($req);

        $q = DB::table('rides')->where('tenant_id', $tenantId);

        if ($s = $req->query('status')) $q->where('status', strtolower($s));
        if ($p = $req->query('phone'))  $q->where('passenger_phone', 'like', "%{$p}%");
        if ($d = $req->query('date'))   $q->whereDate('created_at', $d);

        $rows = $q->orderByDesc('id')->limit(200)->get([
            'id','tenant_id','status','requested_channel',
            'passenger_id','passenger_name','passenger_phone',
            'origin_label','origin_lat','origin_lng',
            'dest_label','dest_lat','dest_lng',
            'fare_mode','payment_method','notes','pax',
            'distance_m','duration_s','route_polyline',
            'quoted_amount','total_amount',
            'driver_id','vehicle_id','sector_id','stand_id','shift_id',
            'scheduled_for','requested_at','accepted_at','arrived_at','onboard_at',
            'finished_at','canceled_at','cancel_reason','canceled_by',
            'created_by','created_at','updated_at',
            'stops_json','stops_count','stop_index',
        ]);

        $rows->transform(function($r){
            $r->stops = [];
            if (!empty($r->stops_json)) {
                $tmp = json_decode($r->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $r->stops = $tmp;
            }
            return $r;
        });

        return response()->json($rows);
    }

    /** GET /api/rides/{ride} */
     public function show(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        $row = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->where('r.id', $ride)
            ->select([
                'r.id','r.tenant_id','r.status','r.requested_channel',
                'r.passenger_id','r.passenger_name','r.passenger_phone',
                'r.origin_label','r.origin_lat','r.origin_lng',
                'r.dest_label','r.dest_lat','r.dest_lng',
                'r.fare_mode','r.payment_method','r.notes','r.pax',
                'r.distance_m','r.duration_s','r.route_polyline',
                'r.quoted_amount','r.total_amount',
                'passenger_offer','driver_offer','agreed_amount',

                'r.driver_id','r.vehicle_id','r.sector_id','r.stand_id','r.shift_id',
                'r.scheduled_for','r.requested_at','r.accepted_at','r.arrived_at','r.onboard_at',
                'r.finished_at','r.canceled_at','r.cancel_reason','r.canceled_by',
                'r.created_by','r.created_at','r.updated_at',
                'r.stops_json','r.stops_count','r.stop_index',
            ])
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Ride no encontrado'], 404);
        }

        // cast básicos
        $row->id        = (int)$row->id;
        $row->tenant_id = (int)$row->tenant_id;

        $row->passenger_id = isset($row->passenger_id) ? (int)$row->passenger_id : null;
        $row->driver_id    = isset($row->driver_id)    ? (int)$row->driver_id    : null;
        $row->vehicle_id   = isset($row->vehicle_id)   ? (int)$row->vehicle_id   : null;
        $row->sector_id    = isset($row->sector_id)    ? (int)$row->sector_id    : null;
        $row->stand_id     = isset($row->stand_id)     ? (int)$row->stand_id     : null;
        $row->shift_id     = isset($row->shift_id)     ? (int)$row->shift_id     : null;

        $row->stops = $row->stops_json ? (json_decode($row->stops_json, true) ?: []) : [];

        $row->origin_lat = isset($row->origin_lat) ? (float)$row->origin_lat : null;
        $row->origin_lng = isset($row->origin_lng) ? (float)$row->origin_lng : null;
        $row->dest_lat   = isset($row->dest_lat)   ? (float)$row->dest_lat   : null;
        $row->dest_lng   = isset($row->dest_lng)   ? (float)$row->dest_lng   : null;

        $row->distance_m = isset($row->distance_m) ? (int)$row->distance_m : null;
        $row->duration_s = isset($row->duration_s) ? (int)$row->duration_s : null;

        $row->quoted_amount = isset($row->quoted_amount) ? (float)$row->quoted_amount : null;
        $row->total_amount  = isset($row->total_amount)  ? (float)$row->total_amount  : null;

        $row->passenger_offer = isset($row->passenger_offer) ? (float)$row->passenger_offer : null;
        $row->driver_offer    = isset($row->driver_offer)    ? (float)$row->driver_offer    : null;
        $row->agreed_amount   = isset($row->agreed_amount)   ? (float)$row->agreed_amount   : null;
        $row->amount          = $this->resolveFinalAmount($row);

        $row->stops_count = isset($row->stops_count) ? (int)$row->stops_count : 0;
        $row->stop_index  = isset($row->stop_index)  ? (int)$row->stop_index  : 0;

        return response()->json($row);
    }


    // ... (el resto de tus métodos existentes se mantienen igual)
    // POST /api/rides
    // PATCH /api/rides/{ride}/stops
    // POST /api/driver/rides/{ride}/arrived
    // POST /api/driver/rides/{ride}/board
    // POST /api/driver/rides/{ride}/finish
    // POST /api/driver/rides/{ride}/cancel
    // POST /api/rides/{ride}/stops/complete
    // private function commitStatusChange
    private function passengerRatingSummary(int $tenantId, int $passengerId): ?object
    {
        return DB::table('ratings as r')
            ->where('r.tenant_id', $tenantId)
            ->where('r.rated_type', 'passenger')
            ->where('r.rated_id', $passengerId)
            ->selectRaw('
                ROUND(AVG(r.rating), 1) as avg_rating,
                COUNT(*) as total_ratings
            ')
            ->first();
    }

  /** POST /api/rides */
    public function store(Request $req)
    {
        $data = $req->validate([
            'passenger_name'  => 'nullable|string|max:120',
            'passenger_phone' => 'nullable|string|max:40',

            'origin_label' => 'nullable|string|max:160',
            'origin_lat'   => 'required|numeric',
            'origin_lng'   => 'required|numeric',

            'dest_label' => 'nullable|string|max:160',
            'dest_lat'   => 'nullable|numeric',
            'dest_lng'   => 'nullable|numeric',

            'stops'          => 'nullable|array|max:2',
            'stops.*.lat'    => 'required_with:stops|numeric',
            'stops.*.lng'    => 'required_with:stops|numeric',
            'stops.*.label'  => 'nullable|string|max:160',

            'payment_method'   => 'nullable|in:cash,transfer,card,corp',
            'fare_mode'        => 'nullable|in:meter,fixed',
            'notes'            => 'nullable|string|max:500',
            'pax'              => 'nullable|integer|min:1|max:10',
            'scheduled_for'    => 'nullable|date',

            'quoted_amount'     => 'nullable|numeric',
            'distance_m'        => 'nullable|integer',
            'duration_s'        => 'nullable|integer',
            'route_polyline'    => 'nullable|string',
            'requested_channel' => 'nullable|in:dispatch,passenger_app,driver_app,api',
        ]);

        $tenantId = $this->tenantIdFrom($req);

        // Default de canal si viene vacío → dispatch
        $channel = $data['requested_channel'] ?? 'dispatch';
        $data['requested_channel'] = $channel;

        // Si hay quoted_amount, forzamos fare_mode = fixed
        if (array_key_exists('quoted_amount', $data) && $data['quoted_amount'] !== null) {
            $data['fare_mode'] = 'fixed';
        }

        /**
         * 🔒 Anti doble click / doble submit (sólo para dispatch)
         *
         * Si en los últimos N segundos ya existe un ride con:
         * - mismo tenant
         * - canal = dispatch
         * - misma lat/lng de origen
         * - mismo teléfono (o ambos null)
         * - mismo destino (cuando exista)
         * - misma quoted_amount
         *
         * devolvemos ese ride en lugar de crear otro.
         */
        if ($channel === 'dispatch') {
            $windowSeconds = 8; // puedes subir/bajar este valor según lo que veas en producción

            $recent = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('requested_channel', 'dispatch')
                ->where('origin_lat', $data['origin_lat'])
                ->where('origin_lng', $data['origin_lng'])
                ->when(!empty($data['dest_lat']) && !empty($data['dest_lng']), function ($q) use ($data) {
                    $q->where('dest_lat', $data['dest_lat'])
                      ->where('dest_lng', $data['dest_lng']);
                })
                ->where(function ($q) use ($data) {
                    // Si mandas teléfono, lo usamos; si no, buscamos también null
                    if (array_key_exists('passenger_phone', $data)) {
                        $q->where('passenger_phone', $data['passenger_phone'] ?? null);
                    } else {
                        $q->whereNull('passenger_phone');
                    }
                })
                ->when(array_key_exists('quoted_amount', $data), function ($q) use ($data) {
                    $q->where('quoted_amount', $data['quoted_amount']);
                })
                ->where('created_at', '>=', now()->subSeconds($windowSeconds))
                ->orderByDesc('id')
                ->first();

            if ($recent) {
                // Opcional: log para depurar
                \Log::info('⛔ Ride dispatch duplicado bloqueado por ventana corta', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $recent->id,
                ]);

                // Devolvemos el ride encontrado (estilo "idempotente")
                return response()->json($recent, 200);
            }
        }

        // --- Crear ride normalmente ---
        $ride = app(\App\Services\CreateRideService::class)->create($data, $tenantId);

        // Stops (conserva label)
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

            app(\App\Services\QuoteRecalcService::class)->recalcWithStops($ride->id, $tenantId);

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

        return response()->json($ride, 201);
    }



    /** PATCH /api/rides/{ride}/stops  (panel) */
    public function updateStops(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        $v = $req->validate([
            'stops'         => 'nullable|array|max:2',
            'stops.*.lat'   => 'required_with:stops|numeric',
            'stops.*.lng'   => 'required_with:stops|numeric',
            'stops.*.label' => 'nullable|string|max:160',
        ]);

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)->where('id', $ride)
            ->lockForUpdate()->first();

        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        if (in_array(strtolower($row->status), ['accepted','en_route','enroute','arrived','on_board','onboard','finished','canceled'])) {
            return response()->json(['ok'=>false,'msg'=>'No se pueden editar paradas después de aceptación'], 409);
        }

        $stops = $v['stops'] ?? [];
        $stops = array_values(array_slice(array_map(function ($s) {
            return [
                'lat'   => (float)$s['lat'],
                'lng'   => (float)$s['lng'],
                'label' => isset($s['label']) ? (trim((string)$s['label']) ?: null) : null,
            ];
        }, $stops), 0, 2));

        DB::table('rides')
            ->where('tenant_id', $tenantId)->where('id', $ride)
            ->update([
                'stops_json'  => $stops ? json_encode($stops) : null,
                'stops_count' => count($stops),
                'stop_index'  => 0,
                'updated_at'  => now(),
            ]);

        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)->where('ride_id', $ride)
            ->where('status', 'offered')
            ->update(['status' => 'released', 'responded_at' => now(), 'updated_at' => now()]);

        app(\App\Services\QuoteRecalcService::class)->recalcWithStops($ride, $tenantId);

        DB::table('ride_status_history')->insert([
            'tenant_id'   => $tenantId,
            'ride_id'     => $ride,
            'prev_status' => strtolower($row->status ?? null),
            'new_status'  => 'stops_updated',
            'meta'        => json_encode(['count' => count($stops), 'stops' => $stops]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['ok' => true, 'stops_count' => count($stops), 'stops' => $stops]);
    }

    /** POST /api/driver/rides/{ride}/arrived */
    public function arrive(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        try {
            DB::statement('CALL sp_ride_arrived_v1(?,?)', [$tenantId, $ride]);
        } catch (\Throwable $e) {
            $this->commitStatusChange($tenantId, $ride, 'arrived', ['source' => 'api.fallback']);
        }

        // Broadcast
        RideBroadcaster::arrived($tenantId, $ride);

        // Señal directa al driver del usuario
        $driverId = DB::table('drivers')->where('tenant_id',$tenantId)->where('user_id',$req->user()->id)->value('id');
        if ($driverId) Realtime::toDriver($tenantId, (int)$driverId)->emit('ride.arrived', ['ride_id'=>$ride]);

        return response()->json(['ok' => true, 'ride_id' => $ride, 'status' => 'arrived']);
    }

    /** POST /api/driver/rides/{ride}/board */
   public function board(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        try {
            DB::statement('CALL sp_ride_board_v1(?,?)', [$tenantId, $ride]);
        } catch (\Throwable $e) {
            // canónico: on_board
            $this->commitStatusChange($tenantId, $ride, 'on_board', ['source' => 'api.fallback']);
        }

        // 🔹 Asegurar timestamps del pasajero cuando el driver marca ON_BOARD
        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->update([
                // por si el SP/commitStatusChange no lo llenó
                'onboard_at'           => DB::raw('COALESCE(onboard_at, NOW())'),
                'passenger_onway_at'   => DB::raw('COALESCE(passenger_onway_at, NOW())'),
                'passenger_onboard_at' => DB::raw('COALESCE(passenger_onboard_at, NOW())'),
            ]);

        RideBroadcaster::onboard($tenantId, $ride);

        $driverId = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $req->user()->id)
            ->value('id');

        if ($driverId) {
            Realtime::toDriver($tenantId, (int)$driverId)
                ->emit('ride.on_board', ['ride_id' => $ride]);
        }

        return response()->json([
            'ok'     => true,
            'ride_id'=> $ride,
            'status' => 'on_board',
        ]);
    }





    /** POST /api/driver/rides/{ride}/finish */
    public function finish(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        // 1) Ejecuta SP de cierre + promoción (usa ride_id SIEMPRE)
        $row  = DB::selectOne('CALL sp_ride_finish_v2(?,?)', [$tenantId, $ride]);
        $mode = $row->mode ?? 'finished';
        $next = $row->next_ride_id ?? null;

        // 1.b) Marcar quién cerró el viaje
        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->update([
                'finished_by' => 'driver',
                'updated_at'  => now(),
            ]);


        // 2) Snapshot del ride tras el SP (para finished + wallet)
        $snap = DB::table('rides')
            ->where('id', $ride)
            ->select(
                'requested_channel',
                'total_amount',
                'quoted_amount',
                'passenger_offer',
                'agreed_amount',
                'driver_id'
            )
            ->first();

        // 2.b) Si total_amount sigue NULL, lo canonicalizamos
        if ($snap && $snap->total_amount === null) {
            $final = $this->resolveFinalAmount($snap);
            if ($final !== null) {
                DB::table('rides')
                    ->where('id', $ride)
                    ->update([
                        'total_amount' => $final,
                        'updated_at'   => now(),
                    ]);
                $snap->total_amount = $final;
            }
        }

        // 3) Emitir evento canónico (ya con total_amount fijo)
        RideBroadcaster::finished(
            $tenantId,
            $ride,
            $snap->total_amount ?? $snap->quoted_amount ?? null
        );


               // 3.b) AutoKick al terminar: usa driver_id del snap (canonical)
        $driverId = (int)($snap->driver_id ?? 0);

        if ($driverId > 0) {
            try {
        $d = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->first(['last_lat','last_lng']);

        if ($d && $d->last_lat !== null && $d->last_lng !== null) {

            \Log::info('finish.autokick IN', [
                'tenant_id' => $tenantId,
                'ride_id'   => $ride,
                'driver_id' => $driverId,
                'lat'       => (float)$d->last_lat,
                'lng'       => (float)$d->last_lng,
            ]);

            $ak = AutoKickService::kickNearestRideForDriver(
                tenantId: (int)$tenantId,
                driverId: (int)$driverId,
                lat: (float)$d->last_lat,
                lng: (float)$d->last_lng
            );

            \Log::info('finish.autokick OUT', [
                'tenant_id' => $tenantId,
                'ride_id'   => $ride,
                'driver_id' => $driverId,
                'res'       => $ak,
            ]);

        } else {
            \Log::warning('finish.autokick SKIP no driver location', [
                'tenant_id' => $tenantId,
                'ride_id'   => $ride,
                'driver_id' => $driverId,
            ]);
        }
            } catch (\Throwable $e) {
                \Log::error('finish.autokick FAIL', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $ride,
                    'driver_id' => $driverId,
                    'err'       => $e->getMessage(),
                ]);
            }
        } else {
            \Log::warning('finish.autokick SKIP no driverId on ride', [
                'tenant_id' => $tenantId,
                'ride_id'   => $ride,
            ]);
        }




        // 4) Wallet SOLO para tenant global (commission mode)
        $globalTenantId = (int) config('cabcontrol.global_tenant_id', 100);

        if ((int) $tenantId === $globalTenantId) {
            // Cargamos el modelo con tenant y billingProfile
            $rideModel = Ride::with(['tenant.billingProfile'])->find($ride);

            if ($rideModel) {
                try {
                    $this->walletService->handleRideFinished($rideModel);
                } catch (\Throwable $e) {
                    Log::error('Error en DriverWalletService::handleRideFinished', [
                        'ride_id'   => $ride,
                        'tenant_id' => $tenantId,
                        'error'     => $e->getMessage(),
                    ]);
                    // Importante: NO rompemos el flujo; el viaje ya está cerrado.
                }
            } else {
                Log::warning('Ride no encontrado para handleRideFinished', [
                    'ride_id'   => $ride,
                    'tenant_id' => $tenantId,
                ]);
            }
        }

        // 5) Realtime adicional (promoted / finished)
        try {
            if ($mode === 'promoted' && $next) {
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('ride.promoted', ['ride_id' => (int)$next]);
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('queue.remove', ['ride_id' => (int)$next]);
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('offers.update', [
                    'ride_id' => (int)$next, 'status' => 'accepted'
                ]);
                if ($mode === 'queued') {  // Si el SP devuelve mode = 'queued'
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('ride.queued', [
                    'ride_id' => (int)$next,
                    'position' => 1 // O obtener posición real de la cola
                        ]);
                    }
                    } else {
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('ride.finished', ['ride_id' => (int)$ride]);
            }
        } catch (\Throwable $e) {
            // Polling fallback
        }

        return response()->json([
            'ok'       => true,
            'ride_id'  => $ride,
            'status'   => 'finished',
            'promoted' => $next,
        ]);
    }

    /** POST /api/driver/rides/{ride}/cancel */
    public function cancelByDriver(Request $req, int $ride)
    {
        $data = $req->validate(['reason' => 'nullable|string|max:160']);

        $user = $req->user();
        $tenantId = $this->tenantIdFrom($req);

        $row = DB::table('rides')->where('tenant_id', $tenantId)->where('id', $ride)->lockForUpdate()->first();
        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        $status = strtolower($row->status ?? '');
        if (in_array($status, ['finished', 'canceled'])) return response()->json(['ok' => true]);

        // valida ownership driver
        $driverId = DB::table('drivers')->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
        if ((int)$row->driver_id !== (int)$driverId) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
        }

        DB::transaction(function () use ($tenantId, $row, $data, $driverId) {
            DB::table('rides')->where('tenant_id', $tenantId)->where('id', $row->id)->update([
                'status'        => 'canceled',
                'canceled_at'   => now(),
                'cancel_reason' => $data['reason'] ?? null,
                'canceled_by'   => 'driver',
                'updated_at'    => now(),
            ]);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $row->id,
                'prev_status' => strtolower($row->status ?? null),
                'new_status'  => 'canceled',
                'meta'        => json_encode(['reason' => $data['reason'] ?? null, 'by' => 'driver']),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('drivers')->where('tenant_id', $tenantId)->where('id', $driverId)
                ->update(['status' => 'idle', 'updated_at' => now()]);

            DB::table('ride_offers')->where('tenant_id', $tenantId)->where('ride_id', $row->id)
                ->where('status', 'offered')
                ->update(['status' => 'released', 'responded_at' => now(), 'updated_at' => now()]);
        });

        RideBroadcaster::canceled($tenantId, (int)$row->id, 'driver', $data['reason'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** POST /api/rides/{ride}/stops/complete  (driver) */
    public function completeStop(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        $row = DB::table('rides')->where('tenant_id', $tenantId)->where('id', $ride)->lockForUpdate()->first();
        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        if ($row->stops_count == 0) return response()->json(['ok' => false, 'msg' => 'Ride sin paradas'], 409);
        if ($row->stop_index >= $row->stops_count) return response()->json(['ok' => true, 'msg' => 'Todas las paradas ya completadas']);

        $stops   = $row->stops_json ? json_decode($row->stops_json, true) : [];
        $idx     = (int)$row->stop_index;
        $current = $stops[$idx] ?? null;

        DB::table('rides')->where('tenant_id', $tenantId)->where('id', $ride)->update([
            'stop_index' => $idx + 1,
            'updated_at' => now(),
        ]);

        DB::table('ride_status_history')->insert([
            'tenant_id'   => $tenantId,
            'ride_id'     => $ride,
            'prev_status' => $row->status,
            'new_status'  => 'stop_done',
            'meta'        => json_encode([
                'seq' => $idx + 1,
                'lat' => $current['lat'] ?? null,
                'lng' => $current['lng'] ?? null,
            ]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        RideBroadcaster::stopDone($tenantId, $ride, $idx + 1);

        return response()->json(['ok' => true, 'stop_index' => $idx + 1, 'stops_count'=> $row->stops_count]);
    }

    /** Núcleo de cambios de estado (canónico: on_board) */
    private function commitStatusChange(int $tenantId, int $rideId, string $toStatus, array $meta = [])
    {
        // Normaliza 'onboard' → 'on_board'
        if ($toStatus === 'onboard') $toStatus = 'on_board';

        return DB::transaction(function () use ($tenantId, $rideId, $toStatus, $meta) {
            $row = DB::table('rides')->where('tenant_id', $tenantId)->where('id', $rideId)->lockForUpdate()->first();
            if (!$row) abort(404, 'Ride no encontrado');

            $fromStatus = strtolower($row->status ?? '');
            $now = now();

            $updates = ['status' => $toStatus, 'updated_at' => $now];
            switch ($toStatus) {
                case 'arrived':   $updates['arrived_at']  = $now; break;
                case 'on_board':  $updates['onboard_at']  = $now; break;
                case 'finished':  $updates['finished_at'] = $now; break;
                case 'canceled':  $updates['canceled_at'] = $updates['canceled_at'] ?? $now; break;
            }

            DB::table('rides')->where('tenant_id', $tenantId)->where('id', $rideId)->update($updates);

            DB::table('ride_status_history')->insert([
                'tenant_id'   => $tenantId,
                'ride_id'     => $rideId,
                'prev_status' => $fromStatus ?: null,
                'new_status'  => $toStatus,
                'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            return [$fromStatus, $toStatus];
        });
    }


   public function confirmAcceptance(Request $req, int $rideId)
    {
        $v = $req->validate([
            'tenant_id' => 'required|integer',
            'driver_id' => 'required|integer',
            'offer_id'  => 'required|integer',
        ]);

        $tenantId = (int)$v['tenant_id'];
        $driverId = (int)$v['driver_id'];

        // Verificar que el driver es el ganador
        $rideRow = DB::table('rides')
            ->where('id', $rideId)
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->first();

        if (!$rideRow) {
            return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
        }

        // Enviar primera ubicación del driver
        $driverLocation = DB::table('driver_locations')
            ->where('driver_id', $driverId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        if ($driverLocation) {
            RideBroadcaster::location(
                $tenantId,
                (int)$rideRow->id,
                (float)$driverLocation->lat,
                (float)$driverLocation->lng,
                $driverLocation->bearing ? (float)$driverLocation->bearing : null
            );
        }

            self::update($tenantId, $rideId, 'accepted', $extra);

      // Reutilizamos el mismo flujo
        RideBroadcaster::bootstrapLocationAndRoute($tenantId, $rideId, $driverId);

       

        return response()->json(['ok' => true]);
    }

    protected function broadcastDriverToPickupRoute(int $tenantId, int $rideId, int $driverId): void
    {
        $ride = DB::table('rides')->where('id', $rideId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$ride || !$ride->origin_lat || !$ride->origin_lng) {
            return;
        }

        $driverLocation = DB::table('driver_locations')
            ->where('driver_id', $driverId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();

        if (!$driverLocation) {
            return;
        }

        // Llamas a tu servicio de rutas (Google/OSRM)
        $route = app(\App\Services\RouteEstimator::class)
            ->estimate(
                originLat:  (float)$driverLocation->lat,
                originLng:  (float)$driverLocation->lng,
                destLat:    (float)$ride->origin_lat,
                destLng:    (float)$ride->origin_lng,
            );

        if (!$route || empty($route->polyline)) {
            return;
        }

        RideBroadcaster::driverToPickupRoute(
            $tenantId,
            $rideId,
            $route->polyline,
            $route->distance_m,
            $route->duration_s,
        );
    }


    public function driverCardForPassenger(Request $r, int $ride)
    {
        $tenantId    = (int)($r->input('tenant_id') ?? $this->tenantIdFrom($r));
        $firebaseUid = $r->input('firebase_uid');

        \Log::info('driverCardForPassenger IN', [
            'tenant_id'    => $tenantId,
            'ride'         => $ride,
            'firebase_uid' => $firebaseUid,
        ]);

        $rideRow = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first();

        if (!$rideRow) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Ride no encontrado',
            ], 404);
        }

        // Si todavía no tiene conductor asignado
        if (!$rideRow->driver_id) {
            return response()->json([
                'ok'                      => true,
                'driver_id'                =>null, 
                'driver_name'             => null,
                'driver_phone'             => null,
                'avatar_url'              => null,
                'rating'                  => null,
                'total_trips'             => null,
                'car_brand'               => null,
                'car_model'               => null,
                'car_year'                => null,
                'car_color'               => null,
                'plate'                   => null,
                'economico'               => null,
                'eta_minutes'             => null, // compat
                'eta_pickup_minutes'      => null,
                'eta_destination_minutes' => null,
            ]);
        }

        // ---- DRIVER ----
        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideRow->driver_id)
            ->first();

        // ---- VEHÍCULO (turno / asignación fallback) ----
        $vehicleId = $rideRow->vehicle_id;

        if (!$vehicleId) {
            $shift = DB::table('driver_shifts')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $rideRow->driver_id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->first();

            if ($shift && $shift->vehicle_id) {
                $vehicleId = $shift->vehicle_id;
            } else {
                $assignment = DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $rideRow->driver_id)
                    ->whereNull('end_at')
                    ->orderByDesc('start_at')
                    ->first();

                if ($assignment && $assignment->vehicle_id) {
                    $vehicleId = $assignment->vehicle_id;
                }
            }
        }

        $vehicle = null;
        if ($vehicleId) {
            $vehicle = DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('id', $vehicleId)
                ->first();

            if ($vehicle && !$rideRow->vehicle_id) {
                DB::table('rides')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $rideRow->id)
                    ->update([
                        'vehicle_id' => $vehicleId,
                        'updated_at' => now(),
                    ]);
            }
        }

        // ---- AVATAR URL ABSOLUTO ----
        $avatarUrl = null;
        if ($driver && !empty($driver->foto_path)) {
            $base = $r->getSchemeAndHttpHost();
            $avatarUrl = $base . '/storage/' . ltrim($driver->foto_path, '/');
        }

        // ---- RATING / TRIPS ----
        $rating     = null;
        $totalTrips = null;

        if ($driver) {
            if (isset($driver->rating_avg)) {
                $rating = (float)$driver->rating_avg;
            } elseif (isset($driver->rating)) {
                $rating = (float)$driver->rating;
            }

            if (isset($driver->rating_count)) {
                $totalTrips = (int)$driver->rating_count;
            } elseif (isset($driver->trips_count)) {
                $totalTrips = (int)$driver->trips_count;
            }

            if ($rating === null) {
                $ratingRow = DB::table('ratings')
                    ->where('tenant_id', $tenantId)
                    // ->where('driver_id', $rideRow->driver_id) // si quieres filtrar por driver
                    ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_trips')
                    ->first();

                if ($ratingRow && $ratingRow->avg_rating !== null) {
                    $rating     = round((float)$ratingRow->avg_rating, 2);
                    $totalTrips = (int)$ratingRow->total_trips;
                }
            }
        }

        // ---- ETA pickup & destino ----
        $etaPickupMinutes      = null;
        $etaDestinationMinutes = null;

        // 1) ETA pickup: driver -> origen
        if (!empty($rideRow->origin_lat) && !empty($rideRow->origin_lng) && $rideRow->driver_id) {
            $loc = DB::table('driver_locations')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $rideRow->driver_id)
                ->orderByDesc('id')            // o updated_at
                ->first();

            if ($loc && $loc->lat && $loc->lng) {
                $distanceKm = $this->haversineKm(
                    (float)$loc->lat,
                    (float)$loc->lng,
                    (float)$rideRow->origin_lat,
                    (float)$rideRow->origin_lng
                );

                $avgSpeedKmh = 22; // 👈 a futuro: tenant_settings

                if ($distanceKm !== null && $distanceKm > 0) {
                    $etaPickupMinutes = max(1, (int)ceil(($distanceKm / $avgSpeedKmh) * 60));
                }
            }
        }

        // Fallback pickup: usamos duration_s pero capeado (no queremos 45 min si es full trip)
        if ($etaPickupMinutes === null && !empty($rideRow->duration_s)) {
            $etaPickupMinutes = max(1, min(10, (int)ceil($rideRow->duration_s / 60)));
        }

        // 2) ETA destino: preferimos driver -> destino si hay coords
        // Ajusta dest_lat/dest_lng si tus columnas se llaman distinto (p. ej. dropoff_lat, dropoff_lng)
        if (!empty($rideRow->dest_lat) && !empty($rideRow->dest_lng) && $rideRow->driver_id) {
            $loc = DB::table('driver_locations')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $rideRow->driver_id)
                ->orderByDesc('id')
                ->first();

            if ($loc && $loc->lat && $loc->lng) {
                $distanceKmDest = $this->haversineKm(
                    (float)$loc->lat,
                    (float)$loc->lng,
                    (float)$rideRow->dest_lat,
                    (float)$rideRow->dest_lng
                );

                $avgSpeedKmh = 22;

                if ($distanceKmDest !== null && $distanceKmDest > 0) {
                    $etaDestinationMinutes = max(1, (int)ceil(($distanceKmDest / $avgSpeedKmh) * 60));
                }
            }
        }

        // Fallback destino: usamos duración total del viaje
        if ($etaDestinationMinutes === null && !empty($rideRow->duration_s)) {
            $etaDestinationMinutes = max(1, (int)ceil($rideRow->duration_s / 60));
        }

        return response()->json([
            'ok'                      => true,
            'driver_id'               =>$driver->id,  
            'driver_name'             => $driver->name ?? null,
             'driver_phone'            => $driver->phone ?? null,
            'avatar_url'              => $avatarUrl,
            'rating'                  => $rating,
            'total_trips'             => $totalTrips,
            'car_brand'               => $vehicle->brand ?? null,
            'car_model'               => $vehicle->model ?? null,
            'car_year'                => $vehicle->year ?? null,
            'car_color'               => $vehicle->color ?? null,
            'plate'                   => $vehicle->plate ?? null,
            'economico'               => $vehicle->economico ?? null,

            // 👇 compat + nuevos campos
            'eta_minutes'             => $etaPickupMinutes,      // compatibilidad
            'eta_pickup_minutes'      => $etaPickupMinutes,      // driver → pickup
            'eta_destination_minutes' => $etaDestinationMinutes, // driver → destino

             // 🔹 Datos para transferencia
            'transfer_bank'           => $driver->payout_bank ?? null,
            'transfer_account'        => $driver->payout_account_number ?? null,
            'transfer_clabe'          => $driver->payout_clabe ?? null,
            'transfer_name'           => $driver->payout_account_name ?? null,
            'transfer_notes'          => $driver->payout_notes ?? null,
        ]);
    }

    /**
     * Distancia Haversine en km entre dos puntos (lat/lng)
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): ?float
    {
        // Si son iguales, cero
        if ($lat1 == $lat2 && $lon1 == $lon2) return 0.0;

        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }



    /** GET /api/rides/{ride}/ratings - Obtener calificaciones del ride */
    public function getRatings(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->get();

        return response()->json([
            'ok' => true,
            'ratings' => $ratings
        ]);
    }

    /** GET /api/driver/history */
    /** GET /api/driver/history */
    public function historyForDriver(Request $req)
    {
        $user = $req->user();
        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenantId = $this->tenantIdFrom($req);

        // Driver asociado al usuario
        $driverId = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->value('id');

        if (!$driverId) {
            return response()->json([
                'ok'      => false,
                'message' => 'Driver no encontrado',
            ], 404);
        }

        // range / payment / channel
        $range   = $req->query('range', $req->query('filter', 'today')); // compat
        $payment = $req->query('payment'); // cash|transfer|card|corp|null
        $channel = $req->query('channel'); // dispatch|passenger_app|driver_app|api|null

        $page    = (int) $req->query('page', 1);
        $perPage = (int) $req->query('per_page', 20);

        $tz  = config('app.timezone');
        $now = now($tz);

        // Rango de fechas
        $from = null;
        $to   = null;

        switch ($range) {
            case 'week':
                $from = $now->copy()->startOfWeek();
                $to   = $now->copy()->endOfDay();
                break;

            case 'month':
                $from = $now->copy()->startOfMonth();
                $to   = $now->copy()->endOfDay();
                break;

            case 'all':
                $range = 'all';
                break;

            case 'today':
            default:
                $from  = $now->copy()->startOfDay();
                $to    = $now->copy()->endOfDay();
                $range = 'today';
                break;
        }

        /**
         * BASE para el resumen (sin joins para que no truene los COUNT/SUM)
         */
        $base = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereIn('status', ['finished', 'canceled']);

        if ($from && $to) {
            $base->whereBetween('created_at', [$from, $to]);
        }

        if ($payment) {
            $base->where('payment_method', $payment);
        }

        if ($channel) {
            $base->where('requested_channel', $channel);
        }

        // --- Resumen (sólo viajes terminados) ---
           $summaryRow = (clone $base)
            ->where('status', 'finished')
            ->selectRaw("
                COUNT(*) as finished_trips,
                SUM(
                    CASE
                        WHEN requested_channel = 'passenger_app'
                            THEN COALESCE(agreed_amount, passenger_offer, total_amount, quoted_amount, 0)
                        ELSE
                            COALESCE(total_amount, agreed_amount, passenger_offer, quoted_amount, 0)
                    END
                ) as gross_amount
            ")
            ->first();


        $canceledCount = (clone $base)
            ->where('status', 'canceled')
            ->count();

        $summary = [
            'finishedTrips' => (int)($summaryRow->finished_trips ?? 0),
            'canceledTrips' => (int)$canceledCount,
            'grossAmount'   => (float)($summaryRow->gross_amount ?? 0),
        ];

        /**
         * BASE para la lista, aquí sí hacemos LEFT JOIN con ratings
         * rating = calificación que el PASAJERO le puso al DRIVER en ese ride.
         */
        $listBase = DB::table('rides as r')
            ->leftJoin('ratings as rt', function ($q) use ($tenantId, $driverId) {
                $q->on('rt.ride_id', '=', 'r.id')
                  ->where('rt.tenant_id', '=', $tenantId)
                  ->where('rt.rated_type', '=', 'driver')
                  ->where('rt.rater_type', '=', 'passenger')
                  ->where('rt.rated_id', '=', $driverId);
            })
            ->where('r.tenant_id', $tenantId)
            ->where('r.driver_id', $driverId)
            ->whereIn('r.status', ['finished', 'canceled']);

        if ($from && $to) {
            $listBase->whereBetween('r.created_at', [$from, $to]);
        }

        if ($payment) {
            $listBase->where('r.payment_method', $payment);
        }

        if ($channel) {
            $listBase->where('r.requested_channel', $channel);
        }

        // --- Paginación ---
        $paginator = $listBase
            ->orderByDesc('r.created_at')
            ->select([
                'r.*',
                'rt.rating as driver_rating',
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        $todayDate = $now->toDateString();

        $trips = collect($paginator->items())->map(function ($r) use ($tz, $todayDate) {
            $createdAt = $r->requested_at ?? $r->created_at;
            $created   = $createdAt ? \Carbon\Carbon::parse($createdAt, $tz) : null;

            if ($created && $created->toDateString() === $todayDate) {
                $dateLabel = 'Hoy, ' . $created->format('d M');
            } else {
                $dateLabel = $created ? $created->format('d M Y') : '';
            }

            $timeLabel = $created ? $created->format('H:i') : '';

            $status = strtolower($r->status ?? '');
            $amount = $this->resolveFinalAmount($r);
            return [
                'id'            => (int)$r->id,
                'startedAt'     => $createdAt,
                'finishedAt'    => $r->finished_at,
                'dateLabel'     => $dateLabel,
                'timeLabel'     => $timeLabel,
                'passengerName' => $r->passenger_name ?? 'Pasajero',
                'originLabel'   => $r->origin_label ?? 'Origen sin nombre',
                'destLabel'     => $r->dest_label ?? null,
                'amount'        => $amount !== null ? $amount : 0.0,
                'rating'        => $r->driver_rating !== null ? (float)$r->driver_rating : null,
                'paymentMethod' => $r->payment_method ?? null,
                'status'        => $status,   // finished | canceled
            ];
        });

        return response()->json([
            'ok' => true,
            'pagination' => [
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'total'     => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => $summary,
            'trips'   => $trips,
        ]);
    }

   /**
 * Resuelve el monto final canónico de un viaje.
 *
 * Reglas:
 * - passenger_app:
 *      agreed_amount -> passenger_offer -> total_amount -> quoted_amount
 * - otros canales (dispatch, driver_app, api):
 *      total_amount -> agreed_amount -> passenger_offer -> quoted_amount
 *
 * Siempre retorna un float entero (round) o null.
 */
private function resolveFinalAmount(?object $rideRow): ?float
{
    if (!$rideRow) {
        return null;
    }

    $channel = $rideRow->requested_channel ?? null;

    $agreed  = $rideRow->agreed_amount     ?? null;
    $paxOff  = $rideRow->passenger_offer   ?? null;
    $total   = $rideRow->total_amount      ?? null;
    $quoted  = $rideRow->quoted_amount     ?? null;

    if ($channel === 'passenger_app') {
        $base = $agreed ?? $paxOff ?? $total ?? $quoted;
    } else {
        $base = $total ?? $agreed ?? $paxOff ?? $quoted;
    }

    if ($base === null) {
        return null;
    }

    return (float) round((float) $base);
}




}