<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminRideController extends Controller
{
    public function index(Request $req)
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $q = DB::table('rides as r')
            ->leftJoin('drivers as d', 'r.driver_id', '=', 'd.id')
            ->leftJoin('passengers as p', 'r.passenger_id', '=', 'p.id')
            ->where('r.tenant_id', $tenantId)
            ->select([
                'r.id','r.status','r.requested_channel',
                DB::raw("COALESCE(p.name, r.passenger_name, 'N/A') as passenger_name"),
                'd.name as driver_name',
                'r.origin_label','r.dest_label',
                'r.total_amount','r.quoted_amount',
                'r.created_at','r.scheduled_for',
            ]);

        if ($s = $req->query('status')) $q->where('r.status', strtolower($s));
        if ($p = $req->query('phone'))  $q->where('r.passenger_phone', 'like', "%{$p}%");
        if ($d = $req->query('date'))   $q->whereDate('r.created_at', $d);

        $rides = $q->orderByDesc('r.id')->paginate(25)->withQueryString();

        return view('admin.rides.index', compact('rides'));
    }

    public function show(int $ride)
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $row = DB::table('rides as r')
            ->leftJoin('drivers as d', 'r.driver_id', '=', 'd.id')
            ->leftJoin('vehicles as v', 'r.vehicle_id', '=', 'v.id')
            ->leftJoin('passengers as p', 'r.passenger_id', '=', 'p.id')
            ->where('r.tenant_id', $tenantId)
            ->where('r.id', $ride)
            ->select([
                'r.*',
                DB::raw("COALESCE(p.name, r.passenger_name, 'N/A') as passenger_name"),
                'd.name as driver_name',
                'v.plate as vehicle_plate',
                'v.brand as vehicle_brand',
                'v.model as vehicle_model',
            ])
            ->first();

        abort_if(!$row, 404);

        $row->stops = [];
        if (!empty($row->stops_json)) {
            $tmp = json_decode($row->stops_json, true);
            $row->stops = (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) ? $tmp : [];
        }

        return view('admin.rides.show', ['ride' => $row]);
    }
}
