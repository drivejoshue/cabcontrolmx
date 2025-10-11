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

    // 1) Última ubicación por driver (sin filtrar por recencia)
    $latestPerDriver = DB::table('driver_locations as dl1')
        ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
        ->groupBy('dl1.driver_id');

    $locs = DB::table('driver_locations as dl')
        ->joinSub($latestPerDriver,'last',function($j){
            $j->on('dl.driver_id','=','last.driver_id')->on('dl.id','=','last.last_id');
        })
        ->select(
            'dl.driver_id',
            'dl.lat','dl.lng',
            'dl.reported_at','dl.heading_deg',
            // flag de frescura (120s)
            DB::raw('CASE WHEN dl.reported_at >= (NOW() - INTERVAL 120 SECOND) THEN 1 ELSE 0 END AS is_fresh')
        );

    // 2) Ride activo más reciente por driver (prioridad por estado)
    $activeRide = DB::table('rides as r')
        ->select('r.driver_id','r.status')
        ->where('r.tenant_id',$tenantId)
        ->whereIn('r.status', ['REQUESTED','SCHEDULED','ASSIGNED','EN_ROUTE','ARRIVED','BOARDING','ONBOARD'])
        ->orderByRaw("FIELD(r.status,'ONBOARD','BOARDING','EN_ROUTE','ARRIVED','ASSIGNED','REQUESTED','SCHEDULED')")
        ->orderByDesc('r.id');

    // 3) Listado de drivers: LEFT JOIN turno abierto y ubicación
    $drivers = DB::table('drivers')
        ->where('drivers.tenant_id',$tenantId)
        // turno abierto opcional (para saber si está en shift)
        ->leftJoin('driver_shifts as ds', function($j){
            $j->on('ds.driver_id','=','drivers.id')->whereNull('ds.ended_at');
        })
        ->leftJoinSub($locs,'loc', function($j){
            $j->on('loc.driver_id','=','drivers.id');
        })
        ->leftJoinSub($activeRide,'ar', function($j){
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
            // mantener el status del driver (offline/idle/busy) tal cual:
            DB::raw('drivers.status as driver_status'),
            DB::raw('ar.status as ride_status'),
            // shift_open real
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
   public function assign(Request $r)
{
    $v = $r->validate([
        'ride_id'   => 'required|integer|exists:rides,id',
        'driver_id' => 'required|integer|exists:drivers,id',
        'expires'   => 'nullable|integer|min:10|max:300', // opcional
    ]);

    $tenantId  = $this->tenantId($r);
    $rideId    = (int)$v['ride_id'];
    $driverId  = (int)$v['driver_id'];
    $expSec    = (int)($v['expires'] ?? 45);

    return DB::transaction(function () use ($tenantId,$rideId,$driverId,$expSec) {

        // 1) Crear/renovar oferta “offered”
        $offer = DB::selectOne('CALL sp_create_offer_v3(?,?,?,?)', [
            $tenantId, $rideId, $driverId, $expSec
        ]);
        if (!$offer || empty($offer->offer_id)) {
            return response()->json(['ok'=>false,'msg'=>'No se pudo ofertar'], 422);
        }

        // 2) Aceptar inmediatamente desde Dispatch
        DB::statement('CALL sp_accept_offer_v3(?)', [ (int)$offer->offer_id ]);

        return response()->json(['ok'=>true, 'offer_id'=>(int)$offer->offer_id]);
    });
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
  
}
