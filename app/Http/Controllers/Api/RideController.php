<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RideController extends Controller
{
    /** GET /api/rides */
    public function index(Request $req)
    {
        $q = Ride::query();

        if ($s = $req->query('status'))     $q->where('status', $s);
        if ($p = $req->query('phone'))      $q->where('passenger_phone', 'like', "%{$p}%");
        if ($d = $req->query('date'))       $q->whereDate('created_at', $d);

        return $q->orderByDesc('id')->limit(200)->get();
    }

    /** POST /api/rides */
     public function store(Request $req)
    {
        $data = $req->validate([
            'passenger_name'  => 'nullable|string|max:120',
            'passenger_phone' => 'nullable|string|max:40',

            'origin_label' => 'nullable|string|max:255',
            'origin_lat'   => 'required|numeric',
            'origin_lng'   => 'required|numeric',

            'dest_label' => 'nullable|string|max:255',
            'dest_lat'   => 'nullable|numeric',
            'dest_lng'   => 'nullable|numeric',

            'payment_method' => 'nullable|string|max:30',
            'fare_mode'      => 'nullable|string|max:30',
            'notes'          => 'nullable|string|max:500',
            'pax'            => 'nullable|integer|min:1|max:10',
            'scheduled_for'  => 'nullable|date',
        ]);

        $tenantId = $req->header('X-Tenant-ID')
            ?? optional($req->user())->tenant_id
            ?? 1;

        $ride = DB::transaction(function () use ($data, $tenantId) {

            // 1) Upsert Passenger si viene teléfono (clave en call center)
            $passengerId = null;
            $snapName    = $data['passenger_name']  ?? null;
            $snapPhone   = $data['passenger_phone'] ?? null;

            if (!empty($snapPhone)) {
                $p = Passenger::firstOrCreate(
                    ['tenant_id'=>$tenantId, 'phone'=>$snapPhone],
                    ['name'=>$snapName]
                );

                // Si después el operador teclea un nombre nuevo, lo actualizamos
                if ($snapName && $snapName !== $p->name) {
                    $p->name = $snapName;
                    $p->save();
                }
                $passengerId = $p->id;
                // Si name no venía, usa el del Passenger
                if (!$snapName) $snapName = $p->name;
            }

            // 2) Crear Ride con snapshot
            $r = new Ride();
            $r->tenant_id       = $tenantId;
            $r->status          = !empty($data['scheduled_for']) ? 'SCHEDULED' : 'REQUESTED';

            $r->passenger_id    = $passengerId;
            $r->passenger_name  = $snapName;
            $r->passenger_phone = $snapPhone;

            $r->origin_label = $data['origin_label'] ?? null;
            $r->origin_lat   = $data['origin_lat'];
            $r->origin_lng   = $data['origin_lng'];

            $r->dest_label   = $data['dest_label'] ?? null;
            $r->dest_lat     = $data['dest_lat']   ?? null;
            $r->dest_lng     = $data['dest_lng']   ?? null;

            $r->payment_method = $data['payment_method'] ?? 'cash';
            $r->fare_mode      = $data['fare_mode']      ?? 'meter';
            $r->notes          = $data['notes']          ?? null;
            $r->pax            = $data['pax']            ?? 1;

            $r->scheduled_for  = $data['scheduled_for']  ?? null;
            $r->requested_at   = now();

            $r->save();
            return $r;
        });

        return response()->json($ride, 201);
    }

    /** GET /api/rides/{ride} */
    public function show(Ride $ride)
    {
        return $ride;
    }

    /** PATCH /api/rides/{ride} */
    public function update(Request $req, Ride $ride)
    {
        $ride->fill($req->only([
            'passenger_name','passenger_phone','passenger_account',
            'origin_label','origin_lat','origin_lng',
            'dest_label','dest_lat','dest_lng',
            'notes','payment_method','fare_mode','pax','scheduled_for'
        ]));
        $ride->save();
        return $ride;
    }

    // === Listas para panel derecho ===
    public function active()
    {
        return Ride::whereIn('status', ['ENROUTE','PICKED','ONGOING'])->latest()->get();
    }
    public function queued()
    {
        return Ride::where('status', 'REQUESTED')->latest()->get();
    }
    public function scheduled()
    {
        return Ride::where('status', 'SCHEDULED')->orderBy('scheduled_for')->get();
    }

    // === Acciones operativas ===
    public function assign(Request $req, Ride $ride)
    {
        $ride->driver_id = $req->input('driver_id');
        $ride->vehicle_id = $req->input('vehicle_id');
        $ride->taxi_stand_id = $req->input('taxi_stand_id');
        $ride->status = 'ASSIGNED';
        $ride->save();

        return $ride;
    }

    public function start(Ride $ride)   { $ride->status = 'ENROUTE'; $ride->save(); return $ride; }
    public function pickup(Ride $ride)  { $ride->status = 'PICKED';  $ride->save(); return $ride; }
    public function drop(Ride $ride)    { $ride->status = 'DROPPED'; $ride->save(); return $ride; }
    public function cancel(Ride $ride)
    {
        $ride->status = 'CANCELLED';
        $ride->save();
        return $ride;
    }
}
