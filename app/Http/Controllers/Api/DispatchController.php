<?php
namespace App\Http\Controllers\API;

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

    public function quote(Request $r){
        $v = $r->validate([
            'origin.label'=>'nullable|string',
            'origin.lat'  =>'required|numeric',
            'origin.lng'  =>'required|numeric',
            'destination.label'=>'nullable|string',
            'destination.lat'  =>'required|numeric',
            'destination.lng'  =>'required|numeric',
            'pax'=>'nullable|integer|min:1|max:6'
        ]);
        $rt = $this->geo->route($v['origin']['lat'],$v['origin']['lng'],$v['destination']['lat'],$v['destination']['lng']);
        $price = $this->tarifar($rt['distance_m'],$rt['duration_s']);
        return ['ok'=>true,'distance_m'=>$rt['distance_m'],'duration_s'=>$rt['duration_s'],'price'=>$price];
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

    private function tarifar(int $m, int $s): float {
        $base = 25; $km = 8; // demo
        return round($base + ($m/1000.0)*$km, 2);
    }

  
  public function active(Request $r)
    {
         $tenantId = $this->tenantId($r);

        // Rides en curso (con tus estatus en MAYÚSCULAS)
        $rides = Ride::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['REQUESTED','SCHEDULED','ASSIGNED','ARRIVED','BOARDING','ONBOARD'])
            ->orderByRaw("FIELD(status,'REQUESTED','SCHEDULED','ASSIGNED','ARRIVED','BOARDING','ONBOARD')")
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id','status','passenger_name','passenger_phone',
                'origin_label','origin_lat','origin_lng',
                'dest_label','dest_lat','dest_lng',
                'payment_method','fare_mode','pax',
                'scheduled_for','requested_at','created_at'
            ]);

        // Colas por paradero: cuenta conductores con turno abierto a ≤200m (no requiere relación)
        $stands = TaxiStand::query()->get(['id','nombre','latitud','longitud']);

        $queues = $stands->map(function ($s) use ($tenantId) {
            $count = DB::table('drivers')
                ->where('drivers.tenant_id', $tenantId)
                ->join('driver_shifts', 'driver_shifts.driver_id', '=', 'drivers.id')
                ->whereNull('driver_shifts.ended_at') // turno abierto (tu schema usa ended_at)
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

    // última ubicación por driver
    $latestPerDriver = DB::table('driver_locations as dl1')
        ->select('dl1.driver_id', DB::raw('MAX(dl1.id) as last_id'))
        ->groupBy('dl1.driver_id');

    $locs = DB::table('driver_locations as dl')
        ->joinSub($latestPerDriver,'last',function($j){
            $j->on('dl.driver_id','=','last.driver_id')->on('dl.id','=','last.last_id');
        })
        ->select('dl.driver_id','dl.lat','dl.lng','dl.reported_at','dl.heading_deg');

    // ride activo por driver (1st por prioridad)
    $activeRide = DB::table('rides as r')
        ->select('r.driver_id','r.status')
        ->where('r.tenant_id',$tenantId)
        ->whereIn('r.status',['REQUESTED','SCHEDULED','ASSIGNED','ARRIVED','BOARDING','ONBOARD'])
        ->orderByRaw("FIELD(r.status,'ONBOARD','ASSIGNED','EN_ROUTE','ARRIVED','REQUESTED','SCHEDULED')")
        ->orderByDesc('r.id');

    $drivers = DB::table('drivers')
        ->where('drivers.tenant_id',$tenantId)
        ->join('driver_shifts as ds',function($j){
            $j->on('ds.driver_id','=','drivers.id')->whereNull('ds.ended_at');
        })
        ->leftJoinSub($locs,'loc',function($j){ $j->on('loc.driver_id','=','drivers.id'); })
        ->leftJoinSub($activeRide,'ar',function($j){ $j->on('ar.driver_id','=','drivers.id'); })
        ->leftJoin('vehicles as v','v.id','=','ds.vehicle_id')
        ->select(
            'drivers.id','drivers.name','drivers.phone',
            DB::raw('loc.lat as lat'), DB::raw('loc.lng as lng'),
            DB::raw('loc.reported_at'), DB::raw('loc.heading_deg'),
            DB::raw('COALESCE(v.type,"sedan") as vehicle_type'),
            DB::raw('v.plate as vehicle_plate'), DB::raw('v.economico as vehicle_economico'),
            DB::raw('COALESCE(drivers.status,"idle") as driver_status'),
            DB::raw('ar.status as ride_status'),
            DB::raw('1 as shift_open') // si quieres mandarlo explícito
        )
        ->orderBy('drivers.id')->limit(500)->get();

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
        $data = $r->validate([
            'ride_id'   => 'required|integer|exists:rides,id',
            'driver_id' => 'required|integer|exists:drivers,id',
        ]);

        $ride = Ride::findOrFail($data['ride_id']);

        // tus estatus de rides al crear son MAYÚSCULAS; acepta asignar si está pendiente
        if (!in_array($ride->status, ['REQUESTED','SCHEDULED','ASSIGNED'])) {
            return response()->json(['ok'=>false,'msg'=>'El viaje no está disponible para asignar.'], 422);
        }

        DB::transaction(function () use ($ride, $data) {
            $ride->driver_id   = $data['driver_id'];
            $ride->status      = 'ASSIGNED';
            $ride->accepted_at = now();
            $ride->save();

            DB::table('drivers')
                ->where('id', $data['driver_id'])
                ->update(['status' => 'busy', 'updated_at' => now()]);
        });

        return response()->json(['ok'=>true]);
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
