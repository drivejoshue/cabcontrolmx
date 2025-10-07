<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AssignmentController;


class DriverController extends Controller
{
    public function index(Request $r)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;
        $q = trim($r->get('q',''));

        $drivers = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->when($q, function($qq) use ($q){
                $qq->where(function($w) use ($q){
                    $w->where('name','like',"%$q%")
                      ->orWhere('phone','like',"%$q%")
                      ->orWhere('email','like',"%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.drivers.index', compact('drivers','q'));
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

    public function store(Request $r)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'name'       => 'required|string|max:120',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:120',
            'document_id'=> 'nullable|string|max:60',
            'active'     => 'nullable|boolean',
            'foto'       => 'nullable|image|max:2048', // 2MB
        ]);

        $fotoPath = null;
        if ($r->hasFile('foto')) {
            // Se guardan en storage/app/public/drivers
            $fotoPath = $r->file('foto')->store('drivers', 'public');
        }

        $id = DB::table('drivers')->insertGetId([
            'tenant_id'   => $tenantId,
            'name'        => $data['name'],
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'status'      => 'offline',
            'foto_path'   => $fotoPath,
            'active'      => (int)($data['active'] ?? 1),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('drivers.show', ['id'=>$id])->with('ok','Conductor creado.');
    }

    public function show(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        // Asignación vigente (si existe)
        $currentAssignment = DB::table('driver_vehicle_assignments as a')
            ->join('vehicles as v','v.id','=','a.vehicle_id')
            ->where('a.tenant_id',$tenantId)
            ->where('a.driver_id',$id)
            ->whereNull('a.end_at')
            ->select([
                'a.id as assignment_id',
                'a.start_at',
                'v.id',
                'v.economico',
                'v.plate',
                'v.brand',
                'v.model',
            ])
            ->first();

        // Histórico
        $assignments = DB::table('driver_vehicle_assignments as a')
            ->join('vehicles as v','v.id','=','a.vehicle_id')
            ->where('a.tenant_id',$tenantId)
            ->where('a.driver_id',$id)
            ->orderByDesc('a.start_at')
            ->select([
                'a.start_at','a.end_at',
                'v.id','v.economico','v.plate','v.brand','v.model',
            ])
            ->get();

        // Para el modal de asignación
        $vehiclesForSelect = DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('active',1)
            ->orderBy('economico')
            ->select('id','economico','brand','model','plate')
            ->get();

        return view('admin.drivers.show', compact(
            'driver','currentAssignment','assignments','vehiclesForSelect'
        ));
    }

    public function edit(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        return view('admin.drivers.edit', compact('driver'));
    }

    public function update(Request $r, int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'name'       => 'required|string|max:120',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:120',
            'document_id'=> 'nullable|string|max:60',
            'active'     => 'nullable|boolean',
            'foto'       => 'nullable|image|max:2048',
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        $fotoPath = $driver->foto_path;
        if ($r->hasFile('foto')) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            $fotoPath = $r->file('foto')->store('drivers', 'public');
        }

        DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->update([
                'name'        => $data['name'],
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'active'      => (int)($data['active'] ?? 1),
                'foto_path'   => $fotoPath,
                'updated_at'  => now(),
            ]);

        return redirect()->route('drivers.show',['id'=>$id])->with('ok','Conductor actualizado.');
    }

    public function destroy(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        // Soft delete recomendado
        DB::table('drivers')
            ->where('id',$id)
            ->update(['active'=>0,'updated_at'=>now()]);

        // Si quisieras borrado físico + foto:
        // if ($driver->foto_path && Storage::disk('public')->exists($driver->foto_path)) {
        //     Storage::disk('public')->delete($driver->foto_path);
        // }
        // DB::table('drivers')->where('id',$id)->delete();

        return redirect()->route('drivers.index')->with('ok','Conductor desactivado.');
    }

    /**
     * Asigna un vehículo a un chofer (crea/actualiza driver_vehicle_assignments)
     */
    public function assignVehicle(Request $r, int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'vehicle_id'     => 'required|integer',
            'start_at'       => 'nullable|date',
            'note'           => 'nullable|string|max:255',
            'close_conflicts'=> 'nullable|boolean',
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        $vehicle = DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('id',$data['vehicle_id'])
            ->first();
        if (!$vehicle) {
            return back()->withErrors(['vehicle_id'=>'Vehículo inválido'])->withInput();
        }

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if (!empty($data['close_conflicts'])) {
                // Cerrar cualquier asignación vigente del chofer
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('driver_id',$id)
                    ->whereNull('end_at')
                    ->update(['end_at'=>$startAt, 'updated_at'=>now()]);
                // Cerrar del vehículo
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('vehicle_id',$data['vehicle_id'])
                    ->whereNull('end_at')
                    ->update(['end_at'=>$startAt, 'updated_at'=>now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id' => $tenantId,
                'driver_id' => $id,
                'vehicle_id'=> $data['vehicle_id'],
                'start_at'  => $startAt,
                'end_at'    => null,
                'note'      => $data['note'] ?? null,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['assign'=>'No se pudo asignar: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('drivers.show',['id'=>$id])->with('ok','Vehículo asignado.');
    }

    /**
     * Cierra una asignación por ID (se usa tanto desde Driver como desde Vehicle)
     */
    public function closeAssignment(int $assignmentId)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $a = DB::table('driver_vehicle_assignments')
            ->where('tenant_id',$tenantId)
            ->where('id',$assignmentId)
            ->first();

        abort_if(!$a, 404);

        DB::table('driver_vehicle_assignments')
            ->where('id',$assignmentId)
            ->update([
                'end_at'    => now(),
                'updated_at'=> now(),
            ]);

        // Redirigir de vuelta a donde venía (driver o vehicle)
        if (url()->previous() && str_contains(url()->previous(), '/admin/vehicles/')) {
            return back()->with('ok','Asignación cerrada.');
        }
        return back()->with('ok','Asignación cerrada.');
    }
}
