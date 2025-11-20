<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RideBroadcaster;
use App\Services\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RideController extends Controller
{
    /** Helper tenant */
    private function tenantIdFrom(Request $req): int
    {
        return (int)($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);
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

            $ride->stops_count = isset($ride->stops_count) ? (int)$ride->stops_count : 0;
            $ride->stop_index  = isset($ride->stop_index)  ? (int)$ride->stop_index  : 0;

            // Limpiar campo stops_json
            unset($ride->stops_json);

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
            ->where('r.tenant_id', $tenantId)->where('r.id', $ride)
            ->select([
                'r.id','r.tenant_id','r.status','r.requested_channel',
                'r.passenger_id','r.passenger_name','r.passenger_phone',
                'r.origin_label','r.origin_lat','r.origin_lng',
                'r.dest_label','r.dest_lat','r.dest_lng',
                'r.fare_mode','r.payment_method','r.notes','r.pax',
                'r.distance_m','r.duration_s','r.route_polyline',
                'r.quoted_amount','r.total_amount',
                'r.driver_id','r.vehicle_id','r.sector_id','r.stand_id','r.shift_id',
                'r.scheduled_for','r.requested_at','r.accepted_at','r.arrived_at','r.onboard_at',
                'r.finished_at','r.canceled_at','r.cancel_reason','r.canceled_by',
                'r.created_by','r.created_at','r.updated_at',
                'r.stops_json','r.stops_count','r.stop_index',
            ])->first();

        if (!$row) return response()->json(['ok' => false, 'message' => 'Ride no encontrado'], 404);

        $row->stops = $row->stops_json ? (json_decode($row->stops_json, true) ?: []) : [];

        $row->origin_lat = isset($row->origin_lat) ? (float)$row->origin_lat : null;
        $row->origin_lng = isset($row->origin_lng) ? (float)$row->origin_lng : null;
        $row->dest_lat   = isset($row->dest_lat)   ? (float)$row->dest_lat   : null;
        $row->dest_lng   = isset($row->dest_lng)   ? (float)$row->dest_lng   : null;

        $row->distance_m = isset($row->distance_m) ? (int)$row->distance_m : null;
        $row->duration_s = isset($row->duration_s) ? (int)$row->duration_s : null;

        $row->quoted_amount = isset($row->quoted_amount) ? (float)$row->quoted_amount : null;
        $row->total_amount  = isset($row->total_amount)  ? (float)$row->total_amount  : null;

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

    // Si viene quoted_amount, forzamos fare_mode = fixed
    if (array_key_exists('quoted_amount', $data) && $data['quoted_amount'] !== null) {
        $data['fare_mode'] = 'fixed';
    }

    /** @var \App\Models\Ride $ride */
    $ride = app(\App\Services\CreateRideService::class)->create($data, $tenantId);

    // ----------------------------
    // Stops (se mantienen igual)
    // ----------------------------
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

    // ==========================================
    // 🔥 Auto-wave inmediata para passenger_app
    // ==========================================
    try {
        // Canal real: primero lo que vino en el request, si no lo que quedó en el ride
        $channel = $data['requested_channel'] ?? $ride->requested_channel ?? null;

        // Solo si es app de pasajero y NO es programado
        if ($channel === 'passenger_app'
            && empty($data['scheduled_for'])
            && empty($ride->scheduled_for)
        ) {
            $settings = \App\Services\AutoDispatchService::settings($tenantId);

            $km       = (float)($settings->radius_km ?? 5);
            $expires  = (int)  ($settings->expires_s  ?? 180);
            $limitN   = (int)  ($settings->limit_n    ?? 6);
            $autoSingle = (bool)($settings->auto_assign_if_single ?? false);

            $result = \App\Services\AutoDispatchService::kickoff(
                tenantId: $tenantId,
                rideId:   (int)$ride->id,
                lat:      (float)$ride->origin_lat,
                lng:      (float)$ride->origin_lng,
                km:       $km,
                expires:  $expires,
                limitN:   $limitN,
                autoAssignIfSingle: $autoSingle,
            );

            // si quieres ver en logs:
            \Log::info('Auto-dispatch passenger_app kickoff', [
                'ride_id' => $ride->id,
                'tenant_id' => $tenantId,
                'settings' => [
                    'km' => $km,
                    'expires' => $expires,
                    'limit_n' => $limitN,
                    'auto_assign_if_single' => $autoSingle,
                ],
                'result' => $result,
            ]);

            // opcional: regresarlo en la respuesta
            $ride->auto_dispatch = $result;
        }
    } catch (\Throwable $e) {
        \Log::error('Auto-dispatch passenger_app FAILED', [
            'ride_id'   => $ride->id ?? null,
            'tenant_id' => $tenantId,
            'msg'       => $e->getMessage(),
        ]);
        // No rompemos el flujo del pasajero: igual regresa 201
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

        RideBroadcaster::onboard($tenantId, $ride);

        $driverId = DB::table('drivers')->where('tenant_id',$tenantId)->where('user_id',$req->user()->id)->value('id');
        if ($driverId) Realtime::toDriver($tenantId,(int)$driverId)->emit('ride.on_board', ['ride_id'=>$ride]);

        return response()->json(['ok' => true, 'ride_id' => $ride, 'status' => 'on_board']);
    }

    /** POST /api/driver/rides/{ride}/finish */
    public function finish(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        // 1) Ejecuta SP de cierre + promoción (usa ride_id SIEMPRE)
        $row  = \DB::selectOne('CALL sp_ride_finish_v1(?,?)', [$tenantId, $ride]);
        $mode = $row->mode ?? 'finished';
        $next = $row->next_ride_id ?? null;

        // 2) Snapshot para DISPATCH si total_amount es NULL
        $snap = DB::table('rides')->where('id',$ride)->select('requested_channel','total_amount','quoted_amount','driver_id')->first();
        if (($snap->requested_channel ?? null) === 'dispatch' && $snap->total_amount === null) {
            DB::table('rides')->where('id',$ride)->update([
                'total_amount' => $snap->quoted_amount,
                'updated_at'   => now(),
            ]);
        }

        // 3) Emitir
        RideBroadcaster::finished($tenantId, $ride, $snap->total_amount ?? $snap->quoted_amount ?? null);

        try {
            if ($mode === 'promoted' && $next) {
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('ride.promoted', ['ride_id' => (int)$next]);
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('queue.remove', ['ride_id' => (int)$next]);
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('offers.update', [
                    'ride_id' => (int)$next, 'status' => 'accepted'
                ]);
            } else {
                Realtime::toDriver($tenantId, (int)$snap->driver_id)->emit('ride.finished', ['ride_id' => (int)$ride]);
            }
        } catch (\Throwable $e) { /* polling fallback */ }

        return response()->json(['ok'=>true, 'ride_id'=>$ride, 'status'=>'finished', 'promoted'=>$next]);
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
}