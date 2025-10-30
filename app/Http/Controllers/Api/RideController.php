<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RideController extends Controller
{
    /** Helper tenant */
    private function tenantIdFrom(Request $req): int
    {
        return (int)($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);
    }

    /** GET /api/rides */
public function index(Request $req)
{
    $tenantId = $this->tenantIdFrom($req);

    $q = DB::table('rides')
        ->where('tenant_id', $tenantId);

    if ($s = $req->query('status')) $q->where('status', strtolower($s));
    if ($p = $req->query('phone'))  $q->where('passenger_phone', 'like', "%{$p}%");
    if ($d = $req->query('date'))   $q->whereDate('created_at', $d);

    $rows = $q->orderByDesc('id')
        ->limit(200)
        ->get([
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
            // ¡IMPORTANTE!
            'stops_json','stops_count','stop_index',
        ]);

    // agregar 'stops' ya decodificado para que el front lo tenga directo
    $rows->transform(function($r){
        $r->stops = [];
        if (!empty($r->stops_json)) {
            $tmp = json_decode($r->stops_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $r->stops = $tmp;
            }
        }
        return $r;
    });

    return response()->json($rows);
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
            'stops.*.label'    => 'nullable|string|max:160', 

            'payment_method'   => 'nullable|in:cash,transfer,card,corp',
            'fare_mode'        => 'nullable|in:meter,fixed',
            'notes'            => 'nullable|string|max:500',
            'pax'              => 'nullable|integer|min:1|max:10',
            'scheduled_for'    => 'nullable|date',

            // calculado por el Dispatch
            'quoted_amount'     => 'nullable|numeric',
            'distance_m'        => 'nullable|integer',
            'duration_s'        => 'nullable|integer',
            'route_polyline'    => 'nullable|string',
            'requested_channel' => 'nullable|in:dispatch,passenger_app,driver_app,api',
        ]);

        $tenantId = $this->tenantIdFrom($req);

        if (array_key_exists('quoted_amount', $data) && $data['quoted_amount'] !== null) {
            $data['fare_mode'] = 'fixed';
        }
        // Crear ride (tu servicio actual)
        $ride = app(\App\Services\CreateRideService::class)->create($data, $tenantId);

        // Guardar stops si vienen
        $stops = $data['stops'] ?? [];
        if (!empty($stops)) {
        // normaliza y limita a 2, CONSERVANDO label
        $stops = array_values(array_slice(array_map(function ($s) {
            return [
                'lat'   => isset($s['lat'])   ? (float)$s['lat']   : null,
                'lng'   => isset($s['lng'])   ? (float)$s['lng']   : null,
                'label' => isset($s['label']) ? trim((string)$s['label']) ?: null : null,
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

            // Recalcular ruta/cotización O -> stops... -> D
            app(\App\Services\QuoteRecalcService::class)->recalcWithStops($ride->id, $tenantId);

            // History
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
            'stops'       => 'nullable|array|max:2',
            'stops.*.lat' => 'required_with:stops|numeric',
            'stops.*.lng' => 'required_with:stops|numeric',
            'stops.*.label' => 'nullable|string|max:160',
        ]);

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)->where('id', $ride)
            ->lockForUpdate()->first();

        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        // bloquear si ya está aceptado o en curso (acepta on_board y onboard por si hay mezcla)
        if (in_array($row->status, ['accepted','en_route','arrived','on_board','onboard','finished','canceled'])) {
            return response()->json(['ok'=>false,'msg'=>'No se pueden editar paradas después de aceptación'], 409);
        }

        $stops = $v['stops'] ?? [];
        $stops = array_values(array_slice(array_map(fn ($s) => ['lat' => (float)$s['lat'], 'lng' => (float)$s['lng']], $stops), 0, 2));

        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->update([
                'stops_json'  => $stops ? json_encode($stops) : null,
                'stops_count' => count($stops),
                'stop_index'  => 0,
                'updated_at'  => now(),
            ]);

        // Invalida ofertas no respondidas (si aplica) y recalcula
        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)->where('ride_id', $ride)
            ->where('status', 'offered')
            ->update(['status' => 'released', 'responded_at' => now(), 'updated_at' => now()]);

        app(\App\Services\QuoteRecalcService::class)->recalcWithStops($ride, $tenantId);

        DB::table('ride_status_history')->insert([
            'tenant_id'   => $tenantId,
            'ride_id'     => $ride,
            'prev_status' => null,
            'new_status'  => 'stops_updated',
            'meta'        => json_encode(['count' => count($stops), 'stops' => $stops]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['ok' => true, 'stops_count' => count($stops), 'stops' => $stops]);
    }

    /** Helper antiguo (si tu UI lo usa) */
    public function setStops(Request $req, int $ride)
    {
        return $this->updateStops($req, $ride);
    }

    /** Legacy simple setter (no tocar) */
    private function touchRideStatus(int $tenantId, int $rideId, string $new)
    {
        $aff = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $rideId)
            ->update([
                'status'     => strtolower($new),
                'updated_at' => now(),
            ]);
        if ($aff === 0) abort(404, 'Ride no encontrado o de otro tenant');
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
        return response()->json(['ok' => true, 'ride_id' => $ride, 'status' => 'arrived']);
    }

    /** POST /api/driver/rides/{ride}/board */
    public function board(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);
        try {
            DB::statement('CALL sp_ride_board_v1(?,?)', [$tenantId, $ride]);
        } catch (\Throwable $e) {
            // normalizamos a 'onboard' (ver commitStatusChange)
            $this->commitStatusChange($tenantId, $ride, 'on_board', ['source' => 'api.fallback']);
        }
        return response()->json(['ok' => true, 'ride_id' => $ride, 'status' => 'on_board']);
    }

    /** POST /api/driver/rides/{ride}/finish */
    public function finish(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);
        try {
            DB::statement('CALL sp_ride_finish_v1(?,?)', [$tenantId, $ride]);
        } catch (\Throwable $e) {
            $this->commitStatusChange($tenantId, $ride, 'finished', ['source' => 'api.fallback']);
        }
        return response()->json(['ok' => true, 'ride_id' => $ride, 'status' => 'finished']);
    }

    /** GET /api/driver/rides/active */
    public function activeForDriver(Request $req)
    {
        $user = $req->user();
        $tenantId = $req->header('X-Tenant-ID') ?? optional($user)->tenant_id ?? 1;

        // driver_id por user_id
        $driverId = DB::table('drivers')->where('user_id', $user->id)->value('id');
        if (!$driverId) {
            return response()->json(['ok' => true, 'item' => null]);
        }

        // Estados de ride que consideramos "activos" (acepta on_board y onboard)
        $activeRideStates = ['accepted', 'en_route', 'arrived', 'on_board', 'onboard'];

        // Caso normal: offer aceptada
        $q = DB::table('ride_offers as o')
            ->join('rides as r', 'r.id', '=', 'o.ride_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.driver_id', $driverId)
            ->where('o.status', 'accepted')
            ->whereIn('r.status', $activeRideStates)
            ->orderByDesc('o.id')
            ->select([
                'o.id as offer_id',
                'o.status as offer_status',
                'o.sent_at',
                'o.responded_at',
                'o.expires_at',
                'o.eta_seconds',
                'o.distance_m',
                'o.round_no',
                'o.is_direct',

                'r.id as ride_id',
                'r.status as ride_status',
                'r.origin_label', 'r.origin_lat', 'r.origin_lng',
                'r.dest_label',   'r.dest_lat',   'r.dest_lng',
                'r.quoted_amount',
                'r.distance_m as ride_distance_m',
                'r.duration_s as ride_duration_s',
                'r.route_polyline',

                // ===== Stops =====
                'r.stops_json','r.stops_count','r.stop_index',
            ]);

        $item = $q->first();

        // Decodificar stops si hay item por offer
        if ($item) {
            $item->origin_lat = isset($item->origin_lat) ? (float)$item->origin_lat : null;
            $item->origin_lng = isset($item->origin_lng) ? (float)$item->origin_lng : null;
            $item->dest_lat   = isset($item->dest_lat)   ? (float)$item->dest_lat   : null;
            $item->dest_lng   = isset($item->dest_lng)   ? (float)$item->dest_lng   : null;

            $item->stops = [];
            if (!empty($item->stops_json)) {
                $tmp = json_decode($item->stops_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $item->stops = $tmp;
                }
            }
        }

        // Fallback: asignación directa
        if (!$item) {
            $r = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $driverId)
                ->whereIn('status', $activeRideStates)
                ->orderByDesc('id')
                ->first();

            if ($r) {
                $stops = [];
                if (!empty($r->stops_json)) {
                    $tmp = json_decode($r->stops_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                        $stops = $tmp;
                    }
                }

                $item = (object)[
                    'offer_id'        => null,
                    'offer_status'    => 'accepted',
                    'sent_at'         => null,
                    'responded_at'    => null,
                    'expires_at'      => null,
                    'eta_seconds'     => null,
                    'distance_m'      => null,
                    'round_no'        => 0,
                    'is_direct'       => 1,

                    'ride_id'         => $r->id,
                    'ride_status'     => $r->status,
                    'origin_label'    => $r->origin_label,
                    'origin_lat'      => isset($r->origin_lat) ? (float)$r->origin_lat : null,
                    'origin_lng'      => isset($r->origin_lng) ? (float)$r->origin_lng : null,
                    'dest_label'      => $r->dest_label,
                    'dest_lat'        => ($r->dest_lat !== null) ? (float)$r->dest_lat : null,
                    'dest_lng'        => ($r->dest_lng !== null) ? (float)$r->dest_lng : null,
                    'quoted_amount'   => $r->quoted_amount,
                    'ride_distance_m' => $r->distance_m,
                    'ride_duration_s' => $r->duration_s,
                    'route_polyline'  => $r->route_polyline,

                    // ===== Stops fallback =====
                    'stops_json'      => $r->stops_json,
                    'stops_count'     => $r->stops_count,
                    'stop_index'      => $r->stop_index,
                    'stops'           => $stops,
                ];
            }
        }

        return response()->json([
            'ok'   => true,
            'item' => $item ?: null,
        ]);
    }

    /** POST /api/driver/rides/{ride}/cancel */
    public function cancelByDriver(Request $req, int $ride)
    {
        $data = $req->validate([
            'reason' => 'nullable|string|max:160',
        ]);

        $user = $req->user();
        $tenantId = $this->tenantIdFrom($req);

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->lockForUpdate()
            ->first();

        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        $status = strtolower($row->status ?? '');
        if (in_array($status, ['finished', 'canceled'])) {
            return response()->json(['ok' => true]);
        }

        // valida que realmente sea su ride
        $driverId = DB::table('drivers')->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
        if ((int)$row->driver_id !== (int)$driverId) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
        }

        DB::transaction(function () use ($tenantId, $row, $data, $driverId) {
            DB::table('rides')
                ->where('tenant_id', $tenantId)->where('id', $row->id)
                ->update([
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

            DB::table('drivers')
                ->where('tenant_id', $tenantId)->where('id', $driverId)
                ->update(['status' => 'idle', 'updated_at' => now()]);

            DB::table('ride_offers')
                ->where('tenant_id', $tenantId)->where('ride_id', $row->id)
                ->where('status', 'offered')
                ->update(['status' => 'released', 'responded_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['ok' => true]);
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
            'r.driver_id','r.vehicle_id','r.sector_id','r.stand_id','r.shift_id',
            'r.scheduled_for','r.requested_at','r.accepted_at','r.arrived_at','r.onboard_at',
            'r.finished_at','r.canceled_at','r.cancel_reason','r.canceled_by',
            'r.created_by','r.created_at','r.updated_at',
            // stops
            'r.stops_json','r.stops_count','r.stop_index',
        ])
        ->first();

    if (!$row) {
        return response()->json(['ok' => false, 'message' => 'Ride no encontrado'], 404);
    }

    // Decodifica stops para que el front no tenga que parsear
    $row->stops = $row->stops_json ? (json_decode($row->stops_json, true) ?: []) : [];

    // Normaliza numéricos
    $row->origin_lat = isset($row->origin_lat) ? (float)$row->origin_lat : null;
    $row->origin_lng = isset($row->origin_lng) ? (float)$row->origin_lng : null;
    $row->dest_lat   = isset($row->dest_lat)   ? (float)$row->dest_lat   : null;
    $row->dest_lng   = isset($row->dest_lng)   ? (float)$row->dest_lng   : null;

    $row->distance_m = isset($row->distance_m) ? (int)$row->distance_m   : null;
    $row->duration_s = isset($row->duration_s) ? (int)$row->duration_s   : null;

    $row->quoted_amount = isset($row->quoted_amount) ? (float)$row->quoted_amount : null;
    $row->total_amount  = isset($row->total_amount)  ? (float)$row->total_amount  : null;

    $row->stops_count = isset($row->stops_count) ? (int)$row->stops_count : 0;
    $row->stop_index  = isset($row->stop_index)  ? (int)$row->stop_index  : 0;

    return response()->json($row);
}


    /** Core para cambios de estado */
    private function commitStatusChange(
        int $tenantId,
        int $rideId,
        string $toStatus,
        array $meta = []
    ) {
        // Normaliza 'on_board' -> 'onboard' para ser consistente
        if ($toStatus === 'on_board') $toStatus = 'on_board';

        return DB::transaction(function () use ($tenantId, $rideId, $toStatus, $meta) {
            $row = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('id', $rideId)
                ->lockForUpdate()
                ->first();

            if (!$row) abort(404, 'Ride no encontrado');
            $fromStatus = strtolower($row->status ?? '');

            $now = now();
            $updates = [
                'status'     => $toStatus,
                'updated_at' => $now,
            ];
            switch ($toStatus) {
                case 'arrived':  $updates['arrived_at']  = $now; break;
                case 'on_board':  $updates['onboard_at']  = $now; break;
                case 'finished': $updates['finished_at'] = $now; break;
                case 'canceled': $updates['canceled_at'] = $updates['canceled_at'] ?? $now; break;
            }

            DB::table('rides')
                ->where('tenant_id', $tenantId)->where('id', $rideId)
                ->update($updates);

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

    /** POST /api/rides/{ride}/stops/complete  (driver) */
    public function completeStop(Request $req, int $ride)
    {
        $tenantId = $this->tenantIdFrom($req);

        $row = DB::table('rides')
            ->where('tenant_id', $tenantId)->where('id', $ride)
            ->lockForUpdate()->first();

        if (!$row) return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);

        if ($row->stops_count == 0) return response()->json(['ok' => false, 'msg' => 'Ride sin paradas'], 409);
        if ($row->stop_index >= $row->stops_count) return response()->json(['ok' => true, 'msg' => 'Todas las paradas ya completadas']);

        $stops   = $row->stops_json ? json_decode($row->stops_json, true) : [];
        $idx     = (int)$row->stop_index;
        $current = $stops[$idx] ?? null;

        DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->update([
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

        return response()->json([
            'ok'         => true,
            'stop_index' => $idx + 1,
            'stops_count'=> $row->stops_count,
        ]);
    }
}
