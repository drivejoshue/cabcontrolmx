<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxiStandController extends Controller
{
    protected function currentTenantId(): int
    {
        $tenantId = Auth::user()->tenant_id ?? null;

        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        return (int) $tenantId;
    }

    public function index(Request $request)
    {
        $tenantId = $this->currentTenantId();

        $stands = DB::table('taxi_stands as t')
            ->leftJoin('sectores as s', function ($q) use ($tenantId) {
                $q->on('s.id', '=', 't.sector_id')
                  ->where('s.tenant_id', '=', $tenantId);
            })
            ->where('t.tenant_id', $tenantId)
            ->select('t.*', 's.nombre as sector_nombre')
            ->orderBy('t.id', 'desc')
            ->paginate(15);

        return view('admin.taxistands.index', ['taxistands' => $stands]);
    }

public function create()
{
    $tenantId = $this->currentTenantId();

    $sectores = DB::table('sectores')
        ->where('tenant_id', $tenantId)
        ->where('activo', 1)
        ->orderBy('nombre')
        ->get();

    $tenantLoc = DB::table('tenants')
        ->where('id', $tenantId)
        ->select('latitud','longitud','coverage_radius_km')
        ->first();

    // ⬇️ añade tenantId
    return view('admin.taxistands.create', compact('sectores','tenantLoc','tenantId'));
}

public function edit(int $id)
{
    $tenantId = $this->currentTenantId();

    $stand = DB::table('taxi_stands')
        ->where('tenant_id', $tenantId)
        ->where('id', $id)
        ->first();

    abort_if(!$stand, 404);

    $sectores = DB::table('sectores')
        ->where('tenant_id', $tenantId)
        ->where('activo', 1)
        ->orderBy('nombre')->get();

    $tenantLoc = DB::table('tenants')
        ->where('id', $tenantId)
        ->select('latitud','longitud','coverage_radius_km')
        ->first();

    // ⬇️ añade tenantId
    return view('admin.taxistands.edit', compact('stand','sectores','tenantLoc','tenantId'));
}


    public function store(Request $request)
    {
        $tenantId = $this->currentTenantId();

        $data = $request->validate([
            'nombre'    => 'required|string|max:120',
            'sector_id' => 'required|integer|exists:sectores,id',
            'latitud'   => 'required|numeric',
            'longitud'  => 'required|numeric',
            'capacidad' => 'nullable|integer|min:0',
        ]);

        $codigo    = strtoupper(Str::slug($data['nombre'], '-')).'-'.random_int(100, 999);
        $qr_secret = 'qr_'.Str::random(20);

        DB::table('taxi_stands')->insert([
            'tenant_id'  => $tenantId,
            'sector_id'  => $data['sector_id'],
            'nombre'     => $data['nombre'],
            'latitud'    => $data['latitud'],
            'longitud'   => $data['longitud'],
            'capacidad'  => $data['capacidad'] ?? null,
            'codigo'     => $codigo,
            'qr_secret'  => $qr_secret,
            
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('taxistands.index')->with('ok', 'Paradero creado.');
    }

   

    public function update(Request $request, int $id)
    {
        $tenantId = $this->currentTenantId();

        $data = $request->validate([
            'nombre'    => 'required|string|max:120',
            'sector_id' => 'required|integer|exists:sectores,id',
            'latitud'   => 'required|numeric',
            'longitud'  => 'required|numeric',
            'capacidad' => 'nullable|integer|min:0',
            'activo'    => 'nullable|boolean',
        ]);

        DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'sector_id'  => $data['sector_id'],
                'nombre'     => $data['nombre'],
                'latitud'    => $data['latitud'],
                'longitud'   => $data['longitud'],
                'capacidad'  => $data['capacidad'] ?? null,
               
                'activo'     => (int)($data['activo'] ?? 1),
                'updated_at' => now(),
            ]);

        return redirect()->route('taxistands.edit', $id)->with('ok', 'Paradero actualizado.');
    }

    public function destroy(int $id)
    {
        $tenantId = $this->currentTenantId();

        DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
              
                'activo'     => 0,
                'updated_at' => now(),
            ]);

        return redirect()->route('taxistands.index')->with('ok', 'Paradero desactivado.');
    }
}
