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

        if ($s = $req->query('status')) $q->where('status', strtolower($s));
        if ($p = $req->query('phone'))  $q->where('passenger_phone', 'like', "%{$p}%");
        if ($d = $req->query('date'))   $q->whereDate('created_at', $d);

        return $q->orderByDesc('id')->limit(200)->get();
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

            'payment_method'   => 'nullable|in:cash,transfer,card,corp',
            'fare_mode'        => 'nullable|in:meter,fixed',
            'notes'            => 'nullable|string|max:500',
            'pax'              => 'nullable|integer|min:1|max:10',
            'scheduled_for'    => 'nullable|date',

            // lo que ya calculó el Dispatch
            'quoted_amount'     => 'nullable|numeric',
            'distance_m'        => 'nullable|integer',
            'duration_s'        => 'nullable|integer',
            'route_polyline'    => 'nullable|string',
            'requested_channel' => 'nullable|in:dispatch,passenger_app,driver_app,api',
        ]);

        $tenantId = $req->header('X-Tenant-ID')
            ?? optional($req->user())->tenant_id
            ?? 1;

        $ride = DB::transaction(function () use ($data, $tenantId) {

            // Upsert Passenger por teléfono
            $passengerId = null;
            $snapName    = $data['passenger_name']  ?? null;
            $snapPhone   = $data['passenger_phone'] ?? null;

            if (!empty($snapPhone)) {
                $p = Passenger::firstOrCreate(
                    ['tenant_id'=>$tenantId, 'phone'=>$snapPhone],
                    ['name'=>$snapName]
                );
                if ($snapName && $snapName !== $p->name) { $p->name = $snapName; $p->save(); }
                $passengerId = $p->id;
                if (!$snapName) $snapName = $p->name;
            }

            // Mini snapshot de tarifa si viene monto
            $snapshot = null;
            if (isset($data['quoted_amount'])) {
                $snapshot = [
                    'source'      => 'dispatch_ui',
                    'computed_at' => now()->toDateTimeString(),
                ];
            }

            // Crear ride usando nombres/enum reales (en minúsculas)
            $r = new Ride();
            $r->tenant_id         = $tenantId;
            $r->status            = !empty($data['scheduled_for']) ? 'scheduled' : 'requested';
            $r->requested_channel = $data['requested_channel'] ?? 'dispatch';

            $r->passenger_id    = $passengerId;
            $r->passenger_name  = $snapName;
            $r->passenger_phone = $snapPhone;

            $r->origin_label = $data['origin_label'] ?? null;
            $r->origin_lat   = (float)$data['origin_lat'];
            $r->origin_lng   = (float)$data['origin_lng'];

            $r->dest_label = $data['dest_label'] ?? null;
            $r->dest_lat   = isset($data['dest_lat']) ? (float)$data['dest_lat'] : null;
            $r->dest_lng   = isset($data['dest_lng']) ? (float)$data['dest_lng'] : null;

            $r->fare_mode      = $data['fare_mode'] ?? 'meter';
            $r->payment_method = $data['payment_method'] ?? 'cash';
            $r->notes          = $data['notes'] ?? null;
            $r->pax            = $data['pax'] ?? 1;

            // métricas / tarifa ya calculadas en el front o /api/dispatch/quote
            $r->distance_m    = $data['distance_m']   ?? null;
            $r->duration_s    = $data['duration_s']   ?? null;
            $r->route_polyline= $data['route_polyline'] ?? null;
            $r->quoted_amount = isset($data['quoted_amount']) ? round($data['quoted_amount']) : null; // enteros
            $r->fare_snapshot = $snapshot ? json_encode($snapshot) : null;

            $r->scheduled_for = $data['scheduled_for'] ?? null;
            $r->requested_at  = now();
            $r->created_at    = now();
            $r->updated_at    = now();

            $r->save();
            return $r;
        });

        return response()->json($ride, 201);
    }
}
