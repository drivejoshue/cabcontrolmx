<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxiStandController extends Controller
{
     public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $stands = DB::table('taxi_stands as t')
            ->leftJoin('sectores as s', function ($q) use ($tenantId) {
                $q->on('s.id', '=', 't.sector_id')->where('s.tenant_id', '=', $tenantId);
            })
            ->where('t.tenant_id', $tenantId)
            ->select('t.*', 's.nombre as sector_nombre')
            ->orderBy('t.id', 'desc')
            ->paginate(15);

        // 👇 Enviamos con el nombre que tu vista espera
        return view('admin.taxistands.index', ['taxistands' => $stands]);
    }

    public function create()
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $sectores = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get();

        return view('admin.taxistands.create', compact('sectores'));
    }

    public function store(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

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
            'active'     => 1,
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('taxistands.index')->with('ok', 'Paradero creado.');
    }

    public function edit(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $stand = DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$stand, 404);

        $sectores = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('activo', 1)
            ->orderBy('nombre')->get();

        return view('admin.taxistands.edit', compact('stand','sectores'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

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
                'active'     => (int)($data['activo'] ?? 1),
                'activo'     => (int)($data['activo'] ?? 1),
                'updated_at' => now(),
            ]);

        return redirect()->route('taxistands.edit', $id)->with('ok', 'Paradero actualizado.');
    }

    /** Desactivar (no borrar físico) */
    public function destroy(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'active' => 0,
                'activo' => 0,
                'updated_at' => now(),
            ]);

        return redirect()->route('taxistands.index')->with('ok', 'Paradero desactivado.');
    }
}
