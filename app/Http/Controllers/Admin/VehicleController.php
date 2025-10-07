<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AssignmentController;

class VehicleController extends Controller
{
    public function index(Request $r)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $q = trim($r->get('q',''));

        $vehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->when($q, function($qq) use ($q){
                $qq->where(function($w) use ($q){
                    $w->where('economico','like',"%$q%")
                      ->orWhere('plate','like',"%$q%")
                      ->orWhere('brand','like',"%$q%")
                      ->orWhere('model','like',"%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.vehicles.index', compact('vehicles','q'));
    }

    public function create()
    {
        return view('admin.vehicles.create');
    }

    public function store(Request $r)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'economico' => 'required|string|max:20',
            'plate'     => 'required|string|max:20',
            'brand'     => 'nullable|string|max:60',
            'model'     => 'nullable|string|max:60',
            'color'     => 'nullable|string|max:40',
            'year'      => 'nullable|integer|min:1970|max:2100',
            'capacity'  => 'nullable|integer|min:1|max:10',
            'policy_id' => 'nullable|string|max:60',
            'active'    => 'nullable|boolean',
            'foto'      => 'nullable|image|max:2048',
        ]);

        // Unicidad por tenant
        $existsEco = DB::table('vehicles')->where('tenant_id',$tenantId)->where('economico',$data['economico'])->exists();
        if ($existsEco) return back()->withErrors(['economico'=>'Ya existe ese número económico.'])->withInput();

        $existsPlate = DB::table('vehicles')->where('tenant_id',$tenantId)->where('plate',$data['plate'])->exists();
        if ($existsPlate) return back()->withErrors(['plate'=>'Ya existe esa placa.'])->withInput();

        $fotoPath = null;
        if ($r->hasFile('foto')) {
            $fotoPath = $r->file('foto')->store('vehicles', 'public'); // public/vehicles/...
        }

        $id = DB::table('vehicles')->insertGetId([
            'tenant_id' => $tenantId,
            'economico' => $data['economico'],
            'plate'     => $data['plate'],
            'brand'     => $data['brand'] ?? null,
            'model'     => $data['model'] ?? null,
            'color'     => $data['color'] ?? null,
            'year'      => $data['year'] ?? null,
            'capacity'  => $data['capacity'] ?? 4,
            'policy_id' => $data['policy_id'] ?? null,
            'foto_path' => $fotoPath,
            'active'    => (int)($data['active'] ?? 1),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return redirect()->route('vehicles.show',$id)->with('ok','Vehículo creado.');
    }

public function show(int $id)
{
    $tenantId = auth()->user()->tenant_id ?? 1;

    $v = DB::table('vehicles')
        ->where('tenant_id', $tenantId)
        ->where('id', $id)
        ->first();
    abort_if(!$v, 404);

    // Choferes vigentes (end_at NULL)
    $currentDrivers = DB::table('driver_vehicle_assignments as a')
        ->join('drivers as d', 'd.id', '=', 'a.driver_id')
        ->where('a.tenant_id', $tenantId)
        ->where('a.vehicle_id', $id)
        ->whereNull('a.end_at')
        ->orderBy('a.start_at', 'desc')
        ->select([
            'a.id as assignment_id',
            'a.start_at',
            'd.id as driver_id',
            'd.name',
            'd.phone',
            'd.foto_path',
        ])
        ->get();

    // Histórico (incluye vigentes también, para tabla completa)
    $assignments = DB::table('driver_vehicle_assignments as a')
        ->join('drivers as d', 'd.id', '=', 'a.driver_id')
        ->where('a.tenant_id', $tenantId)
        ->where('a.vehicle_id', $id)
        ->orderBy('a.start_at', 'desc')
        ->select([
            'a.start_at', 'a.end_at', 'a.note',
            'd.id as driver_id', 'd.name', 'd.phone',
        ])
        ->get();

    return view('admin.vehicles.show', compact('v', 'currentDrivers', 'assignments'));
}


public function assignDriver(Request $r, int $id)
{
    $tenantId = Auth::user()->tenant_id ?? 1;

    $data = $r->validate([
        'driver_id'       => 'required|integer',
        'start_at'        => 'nullable|date',
        'note'            => 'nullable|string|max:255',
        'close_conflicts' => 'nullable|boolean',
    ]);

    $vehicle = DB::table('vehicles')
        ->where('tenant_id',$tenantId)
        ->where('id',$id)
        ->first();
    abort_if(!$vehicle, 404);

    $driver = DB::table('drivers')
        ->where('tenant_id',$tenantId)
        ->where('id',$data['driver_id'])
        ->first();
    if (!$driver) {
        return back()->withErrors(['driver_id'=>'Conductor inválido'])->withInput();
    }

    $startAt = $data['start_at'] ?? now();

    DB::beginTransaction();
    try {
        if (!empty($data['close_conflicts'])) {
            // Cierra asignaciones vigentes del driver
            DB::table('driver_vehicle_assignments')
                ->where('tenant_id',$tenantId)
                ->where('driver_id',$data['driver_id'])
                ->whereNull('end_at')
                ->update(['end_at'=>$startAt,'updated_at'=>now()]);
            // Cierra asignaciones vigentes del vehicle
            DB::table('driver_vehicle_assignments')
                ->where('tenant_id',$tenantId)
                ->where('vehicle_id',$id)
                ->whereNull('end_at')
                ->update(['end_at'=>$startAt,'updated_at'=>now()]);
        }

        DB::table('driver_vehicle_assignments')->insert([
            'tenant_id'  => $tenantId,
            'driver_id'  => $data['driver_id'],
            'vehicle_id' => $id,
            'start_at'   => $startAt,
            'end_at'     => null,
            'note'       => $data['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->withErrors(['assign'=>'No se pudo asignar: '.$e->getMessage()])->withInput();
    }

    return redirect()->route('vehicles.show',['id'=>$id])->with('ok','Chofer asignado.');
}


    public function edit(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $v = DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$id)->first();
        abort_if(!$v, 404);
        return view('admin.vehicles.edit', compact('v'));
    }

    public function update(Request $r, int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'economico' => 'required|string|max:20',
            'plate'     => 'required|string|max:20',
            'brand'     => 'nullable|string|max:60',
            'model'     => 'nullable|string|max:60',
            'color'     => 'nullable|string|max:40',
            'year'      => 'nullable|integer|min:1970|max:2100',
            'capacity'  => 'nullable|integer|min:1|max:10',
            'policy_id' => 'nullable|string|max:60',
            'active'    => 'nullable|boolean',
            'foto'      => 'nullable|image|max:2048',
        ]);

        $v = DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$id)->first();
        abort_if(!$v, 404);

        $existsEco = DB::table('vehicles')
            ->where('tenant_id',$tenantId)->where('economico',$data['economico'])->where('id','<>',$id)->exists();
        if ($existsEco) return back()->withErrors(['economico'=>'Ya existe otro vehículo con ese número económico.'])->withInput();

        $existsPlate = DB::table('vehicles')
            ->where('tenant_id',$tenantId)->where('plate',$data['plate'])->where('id','<>',$id)->exists();
        if ($existsPlate) return back()->withErrors(['plate'=>'Ya existe otro vehículo con esa placa.'])->withInput();

        $fotoPath = $v->foto_path;
        if ($r->hasFile('foto')) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            $fotoPath = $r->file('foto')->store('vehicles', 'public');
        }

        DB::table('vehicles')
            ->where('tenant_id',$tenantId)->where('id',$id)
            ->update([
                'economico' => $data['economico'],
                'plate'     => $data['plate'],
                'brand'     => $data['brand'] ?? null,
                'model'     => $data['model'] ?? null,
                'color'     => $data['color'] ?? null,
                'year'      => $data['year'] ?? null,
                'capacity'  => $data['capacity'] ?? 4,
                'policy_id' => $data['policy_id'] ?? null,
                'foto_path' => $fotoPath,
                'active'    => (int)($data['active'] ?? 1),
                'updated_at'=> now(),
            ]);

        return redirect()->route('vehicles.show',$id)->with('ok','Vehículo actualizado.');
    }

    public function destroy(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $v = DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$id)->first();
        abort_if(!$v, 404);

        // Soft delete recomendado
        DB::table('vehicles')->where('id',$id)->update(['active'=>0,'updated_at'=>now()]);

        // Borrado físico (opcional):
        // if ($v->foto_path && Storage::disk('public')->exists($v->foto_path)) {
        //     Storage::disk('public')->delete($v->foto_path);
        // }
        // DB::table('vehicles')->where('id',$id)->delete();

        return redirect()->route('vehicles.index')->with('ok','Vehículo desactivado.');
    }
}
