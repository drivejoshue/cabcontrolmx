<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\TaxiStand;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AutoDispatchService;

class DispatchController extends Controller
{
    //public function __construct(private GoogleMapsService $geo) {}

     private function tenantId(Request $r): int
    {
        return auth()->user()->tenant_id ?? (int)($r->query('tenant_id', 1));
    }

    /** POST /api/dispatch/quote  (origin/destination -> distancia, duración, $) */
 

    public function quote(Request $r)
{
    $v = $r->validate([
        'origin.lat'      => 'required|numeric',
        'origin.lng'      => 'required|numeric',
        'destination.lat' => 'required|numeric',
        'destination.lng' => 'required|numeric',
        'round_to_step'   => 'nullable|numeric',
    ]);

    // 1) Distancia/tiempo estimados (fallback sin Google/OSRM)
    $lat1 = (float)$v['origin']['lat'];      $lng1 = (float)$v['origin']['lng'];
    $lat2 = (float)$v['destination']['lat']; $lng2 = (float)$v['destination']['lng'];

    $toRad = fn($d) => $d * M_PI / 180;
    $R = 6371000; // m
    $dLat = $toRad($lat2 - $lat1);
    $dLng = $toRad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng/2)**2;
    $c = 2 * asin(min(1, sqrt($a)));
    $distStraight = $R * $c;            // metros línea recta
    $distM = (int) round($distStraight * 1.25); // 25% extra por traza vial (aprox)

    // Velocidad urbana promedio (ajústalo): 24 km/h -> 6.67 m/s
    $speed_mps = 24_000 / 3600;
    $durS = (int) max(180, round($distM / max(1e-6, $speed_mps))); // mínimo 3 min

    // 2) Política de tarifa (si existe)
    $tenantId = (int)($r->header('X-Tenant-ID') ?? optional($r->user())->tenant_id ?? 1);
    $pol = DB::table('tenant_fare_policies')->where('tenant_id', $tenantId)->first();

    $base  = $pol->base_fee      ?? 25;
    $perKm = $pol->per_km        ?? 8;
    $perMin= $pol->per_min       ?? 0;
    $minTot= $pol->min_total     ?? 0;
    $night = 1.0;

    // ventana nocturna configurable (si tienes columnas start/end). Si no, 22–06.
    $now = now()->format('H:i:s');
    $isNight = ($now >= '22:00:00' || $now <= '06:00:00');
    if ($isNight) {
        $night = $pol->night_multiplier ?? 1.0;
    }

    // 3) Cálculo
    $km  = $distM / 1000.0;
    $min = $durS  / 60.0;
    $amount = ($base + $km * $perKm + $min * $perMin);
    if ($minTot > 0 && $amount < $minTot) $amount = $minTot;
    $amount *= $night;

    // 4) Redondeo a paso (pesos enteros por default)
    $step = (float)($r->input('round_to_step', 1.00));
    if ($step > 0) {
        $amount = round($amount / $step) * $step;
    }
    $amount = (int) round($amount); // enteros

    return response()->json([
        'ok'         => true,
        'amount'     => $amount,
        'distance_m' => $distM,
        'duration_s' => $durS,
    ]);
}

    public function store(Request $r){
        $tenantId = auth()->user()->tenant_id ?? 1;
        $v = $r->validate([
            'origin.label'=>'required|string',
            'origin.lat'  =>'required|numeric',
            'origin.lng'  =>'required|numeric',
            'destination.label'=>'required|string',
            'destination.lat'  =>'required|numeric',
            'destination.lng'  =>'required|numeric',
            'pax'=>'nullable|integer|min:1|max:6',
            'notes'=>'nullable|string|max:255',
            'scheduled_at'=>'nullable|date',
            'assign.driver_id'=>'nullable|integer',
        ]);

        $rt    = $this->geo->route($v['origin']['lat'],$v['origin']['lng'],$v['destination']['lat'],$v['destination']['lng']);
        $price = $this->tarifar($rt['distance_m'],$rt['duration_s']);

        $id = DB::table('services')->insertGetId([
            'tenant_id'     => $tenantId,
            'status'        => empty($v['assign']['driver_id']) ? 'offered' : 'accepted',
            'origin_label'  => $v['origin']['label'],
            'origin_lat'    => $v['origin']['lat'],
            'origin_lng'    => $v['origin']['lng'],
            'dest_label'    => $v['destination']['label'],
            'dest_lat'      => $v['destination']['lat'],
            'dest_lng'      => $v['destination']['lng'],
            'distance_m'    => $rt['distance_m'],
            'eta_s'         => $rt['duration_s'],
            'price_total'   => $price,
            'driver_id'     => $v['assign']['driver_id'] ?? null,
            'requested_at'  => now(),
            'scheduled_at'  => $v['scheduled_at'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return ['ok'=>true,'service_id'=>$id,'status'=>empty($v['assign']['driver_id'])?'offered':'accepted'];
    }

  

  
 public function active(Request $r)
{
    $tenantId = $this->tenantId($r);

    // Estatus en minúsculas como en DB
    $order = ['requested','scheduled','assigned','arrived','boarding','onboard'];

   $rides = Ride::query()
  ->where('tenant_id', $tenantId)
  ->whereIn('status', $order)
  ->orderByRaw("FIELD(status, '".implode("','", $order)."')")
  ->orderByDesc('id')
  ->limit(200)
  ->select([
    'id','status','passenger_name','passenger_phone',
    'origin_label','origin_lat','origin_lng',
    'dest_label','dest_lat','dest_lng',
    'payment_method','pax','scheduled_for','requested_at','created_at',
    DB::raw('distance_m+0   as distance_m'),
    DB::raw('duration_s+0   as duration_s'),
    DB::raw('quoted_amount+0 as quoted_amount'),
  ])->get();


    // Colas por paradero
    $stands = TaxiStand::query()->get(['id','nombre','latitud','longitud']);

    $queues = $stands->map(function ($s) use ($tenantId) {
        $count = DB::table('drivers')
            ->where('drivers.tenant_id', $tenantId)
            ->join('driver_shifts', 'driver_shifts.driver_id', '=', 'drivers.id')
            ->whereNull('driver_shifts.ended_at')
            ->join('driver_locations as dl', function ($j) {
                $j->on('dl.driver_id','=','drivers.id')
                  ->whereRaw('dl.id = (SELECT MAX(id) FROM driver_locations WHERE driver_id = drivers.id)');
            })
            ->whereRaw("
                (6371000 * acos(
                    cos(radians(?)) * cos(radians(dl.lat)) * cos(radians(dl.lng) - radians(?))
                    + sin(radians(?)) * sin(radians(dl.lat))
                )) <= 200
            ", [$s->latitud, $s->longitud, $s->latitud])
            ->count();

        return (object)[
            'id'          => $s->id,
            'nombre'      => $s->nombre,
            'latitud'     => (float) $s->latitud,
            'longitud'    => (float) $s->longitud,
            'queue_count' => $count,
        ];
    })->values();

    return response()->json([
        'rides'  => $rides,
        'queues' => $queues,
    ]);
}

public function driversLive(Request $r)
{
    $tenantId = $this->tenantId($r);

    // Última ubicación por driver
    $latestPerDriver = DB::table('driver_locations as dl1')
        ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
        ->groupBy('dl1.driver_id');

    $locs = DB::table('driver_locations as dl')
        ->joinSub($latestPerDriver,'last',function($j){
            $j->on('dl.driver_id','=','last.driver_id')
              ->on('dl.id','=','last.last_id');
        })
        ->select(
            'dl.driver_id',
            'dl.lat','dl.lng','dl.reported_at','dl.heading_deg',
            DB::raw('CASE WHEN dl.reported_at >= (NOW() - INTERVAL 120 SECOND) THEN 1 ELSE 0 END AS is_fresh')
        );

    // A) Rides con driver_id (asignados/en curso) → normaliza a canónico
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
        ->whereNotNull('r.driver_id') // <- solo los que YA están ligados a driver
        ->whereIn(DB::raw('UPPER(r.status)'), [
            'ON_BOARD','ONBOARD','BOARDING','EN_ROUTE','ARRIVED','ACCEPTED','ASSIGNED','REQUESTED','SCHEDULED'
        ]);

    // B) Ofertas vivas (offered) por driver (no expirada)
    $liveOffers = DB::table('ride_offers as o')
        ->join('rides as r','r.id','=','o.ride_id')
        ->select([
            'o.driver_id',
            DB::raw("'offered' as ride_status")
        ])
        ->where('o.tenant_id', $tenantId)
        ->whereRaw('o.expires_at IS NULL OR o.expires_at > NOW()')
        ->where(DB::raw('LOWER(o.status)'),'offered');

    // C) Unión con prioridad: primero estados “fuertes” del ride, luego offered
    $activeForDriver = DB::query()
        ->fromSub(
            $assignedOrInProgress->unionAll($liveOffers),
            'ar'
        )
        ->select('ar.driver_id','ar.ride_status')
        ->orderByRaw("FIELD(ar.ride_status,'on_board','en_route','arrived','accepted','offered','requested','scheduled')")
        ->orderBy('ar.driver_id')
        // Nota: si hubiese múltiples filas por driver (p.ej. offered + accepted),
        // luego haremos DISTINCT por driver con la SELECT exterior.
    ;

    // Ensamble final
    $drivers = DB::table('drivers')
        ->where('drivers.tenant_id',$tenantId)
        ->leftJoin('driver_shifts as ds', function($j){
            $j->on('ds.driver_id','=','drivers.id')->whereNull('ds.ended_at');
        })
        ->leftJoinSub($locs,'loc', function($j){
            $j->on('loc.driver_id','=','drivers.id');
        })
        ->leftJoinSub($activeForDriver,'ar', function($j){
            $j->on('ar.driver_id','=','drivers.id');
        })
        ->leftJoin('vehicles as v','v.id','=','ds.vehicle_id')
        ->select(
            'drivers.id','drivers.name','drivers.phone',
            DB::raw('loc.lat as lat'),
            DB::raw('loc.lng as lng'),
            DB::raw('loc.reported_at'),
            DB::raw('loc.heading_deg'),
            DB::raw('loc.is_fresh'),
            DB::raw('COALESCE(v.type,"sedan") as vehicle_type'),
            DB::raw('v.plate as vehicle_plate'),
            DB::raw('v.economico as vehicle_economico'),
            DB::raw('LOWER(COALESCE(drivers.status,"offline")) as driver_status'),
            DB::raw('ar.ride_status'),
            DB::raw('CASE WHEN ds.id IS NULL THEN 0 ELSE 1 END AS shift_open')
        )
        ->orderBy('drivers.id')
        ->get();

    return response()->json($drivers);
}




    public function nearbyDrivers(Request $r)
    {
        $r->validate(['lat'=>'required|numeric','lng'=>'required|numeric','km'=>'nullable|numeric']);
        $km = $r->km ?? 5;

        // Subquery: última localización POR DRIVER (sin columnas duplicadas)
        $latestPerDriver = DB::table('driver_locations as dl1')
            ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
            ->groupBy('dl1.driver_id');

        $locs = DB::table('driver_locations as dl')
            ->joinSub($latestPerDriver, 'last', function ($j) {
                $j->on('dl.driver_id', '=', 'last.driver_id')
                  ->on('dl.id', '=', 'last.last_id');
            })
            ->select([
                'dl.driver_id',
                'dl.lat',
                'dl.lng',
                'dl.reported_at',
            ]);

        $drivers = DB::table('drivers')
            ->join('driver_shifts as ds', function($j){
                $j->on('ds.driver_id','=','drivers.id')
                  ->whereNull('ds.ended_at')
                  ->where('ds.status','abierto');
            })
            ->joinSub($locs,'loc',function($j){
                $j->on('loc.driver_id','=','drivers.id');
            })
            ->leftJoin('vehicles as v','v.id','=','ds.vehicle_id')
            ->select(
                'drivers.id','drivers.name',
                'loc.lat','loc.lng',
                DB::raw("(6371 * acos(
                    cos(radians(?)) * cos(radians(loc.lat)) *
                    cos(radians(loc.lng) - radians(?)) + sin(radians(?)) * sin(radians(loc.lat))
                )) as distance_km"),
                DB::raw('v.plate as vehicle_plate'),
                DB::raw('v.economico as vehicle_economico')
            )
            ->setBindings([$r->lat,$r->lng,$r->lat])
            ->orderBy('distance_km')
            ->get()
            ->filter(fn($d)=>$d->distance_km <= $km)
            ->values();

        return response()->json($drivers);
    }


    /* =======================
     *  Operaciones: asignar / cancelar
     * ======================= */
  // App/Http/Controllers/Api/DispatchController.php
public function assign(Request $req)
{
    $v = $req->validate([
        'ride_id'   => 'required|integer',
        'driver_id' => 'required|integer',
    ]);

    $tenantId   = (int)($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);
    $rideId     = (int)$v['ride_id'];
    $driverId   = (int)$v['driver_id'];
    $assignedBy = optional($req->user())->id ?? 0;

    try {
        // ✅ Usa v2 (es la que existe en tu dump)
        $row = \DB::selectOne('CALL sp_create_offer_v2(?,?,?,?)', [
            $tenantId, $rideId, $driverId, 45   // expires_sec
        ]);
        return response()->json(['ok'=>true,'via'=>'sp_create_offer_v2','row'=>$row]);

    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $isMissing = str_contains($msg, '1305') || str_contains($msg, 'does not exist');

        if ($isMissing) {
            // Fallback 1: asignación directa controlada por DB
            try {
                \DB::selectOne('CALL sp_assign_direct_v1(?,?,?)', [
                    $tenantId, $rideId, $driverId
                ]);
                return response()->json(['ok'=>true,'via'=>'sp_assign_direct_v1']);
            } catch (\Throwable $e2) {
                // Fallback 2: último recurso (DEV) – sin 'assigned_at' y con estado válido
                try {
                    \DB::beginTransaction();

                    $ride = \DB::table('rides')
                        ->where('tenant_id',$tenantId)->where('id',$rideId)
                        ->lockForUpdate()->first();

                    if (!$ride) throw new \Exception('Ride no encontrado');
                    if (in_array($ride->status, ['canceled','finished'])) {
                        throw new \Exception('Ride no asignable en estado '.$ride->status);
                    }

                    \DB::table('rides')->where('id',$rideId)->update([
                        'driver_id'   => $driverId,
                        'status'      => 'accepted',        // 👈 minúsculas
                        'accepted_at' => now(),             // 👈 existe
                        'updated_at'  => now(),
                    ]);

                    \DB::table('ride_status_history')->insert([
                        'tenant_id'  => $tenantId,
                        'ride_id'    => $rideId,
                        'prev_status'=> $ride->status,
                        'new_status' => 'accepted',
                        'meta'       => json_encode(['driver_id'=>$driverId,'assigned_by'=>$assignedBy]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    \DB::commit();
                    return response()->json(['ok'=>true,'via'=>'direct']);
                } catch (\Throwable $e3) {
                    \DB::rollBack();
                    return response()->json(['ok'=>false,'msg'=>$e3->getMessage()], 500);
                }
            }
        }

        return response()->json(['ok'=>false,'msg'=>$msg], 500);
    }
}




    public function cancel(Request $r)
    {
        $data = $r->validate([
            'ride_id' => 'required|integer|exists:rides,id',
            'reason'  => 'nullable|string|max:160',
        ]);

        $ride = Ride::findOrFail($data['ride_id']);

        if (in_array($ride->status, ['FINISHED','CANCELLED'])) {
            return response()->json(['ok'=>true]);
        }

        DB::transaction(function () use ($ride, $data) {
            $ride->status        = 'CANCELLED';
            $ride->canceled_at   = now();
            $ride->cancel_reason = $data['reason'] ?? null;
            $ride->canceled_by   = 'ops';
            $ride->save();

            if ($ride->driver_id) {
                DB::table('drivers')
                    ->where('id', $ride->driver_id)
                    ->update(['status' => 'idle', 'updated_at' => now()]);
            }
        });

        return response()->json(['ok'=>true]);
    }



public function tick(\Illuminate\Http\Request $req)
{
    $v = $req->validate([
        'ride_id' => 'required|integer',
        'km'      => 'nullable|numeric',
        'limit_n' => 'nullable|integer',
        'expires' => 'nullable|integer',
        'auto_assign_if_single' => 'nullable|boolean',
    ]);

    $tenantId = $req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1;

    $ride = \DB::table('rides')
        ->where('tenant_id',$tenantId)->where('id',$v['ride_id'])->first();
    if (!$ride) return response()->json(['ok'=>false,'msg'=>'Ride no encontrado'],404);

    $cfg = \App\Services\AutoDispatchService::settings($tenantId);

    $res = \App\Services\AutoDispatchService::kickoff(
        tenantId: $tenantId,
        rideId:   (int)$ride->id,
        lat:      (float)$ride->origin_lat,
        lng:      (float)$ride->origin_lng,
        km:       (float)($v['km']      ?? $cfg->radius_km),
        expires:  (int)  ($v['expires'] ?? $cfg->expires_s),
        limitN:   (int)  ($v['limit_n'] ?? $cfg->limit_n),
        autoAssignIfSingle: (bool)($v['auto_assign_if_single'] ?? $cfg->auto_assign_if_single)
    );

    return response()->json(['ok'=>true] + $res);
}




  
}
