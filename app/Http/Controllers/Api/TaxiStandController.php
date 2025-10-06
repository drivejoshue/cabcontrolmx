<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxiStandController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $rows = DB::table('taxi_stands as t')
            ->leftJoin('sectores as s', function ($q) use ($tenantId) {
                $q->on('s.id', '=', 't.sector_id')->where('s.tenant_id', '=', $tenantId);
            })
            ->where('t.tenant_id', $tenantId)
            ->where('t.active', 1)
            ->select(
                't.id','t.nombre','t.latitud','t.longitud','t.capacidad','t.codigo',
                't.qr_secret','t.sector_id',
                DB::raw('COALESCE(s.nombre, "") as sector_nombre')
            )
            ->orderBy('t.id', 'desc')
            ->get();

        // Estructura simple para el front:
        return response()->json($rows);
    }
}
