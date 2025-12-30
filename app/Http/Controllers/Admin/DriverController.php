<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{   

    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) {
            abort(403, 'Usuario sin tenant asignado');
        }
        return (int) $tid;
    }



    public function index(Request $r)
    {
       $tenantId = $this->tenantId();
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
            ->paginate(20)
            ->withQueryString();

        return view('admin.drivers.index', compact('drivers','q'));
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

   public function store(Request $r)
{
    $tenantId = $this->tenantId();

    $data = $r->validate([
        'name'        => 'required|string|max:120',
        'phone'       => 'nullable|string|max:30',
        'email'       => 'nullable|email|max:120',          // email de contacto (driver)
        'document_id' => 'nullable|string|max:60',
        'active'      => 'nullable|boolean',
        'foto'        => 'nullable|image|max:2048',

        // Cuenta (usuario)
        'create_user' => 'nullable|boolean',
        'user_email'  => [
            'nullable','email','max:120',
            Rule::unique('users','email')->where(fn($q)=>$q->where('tenant_id',$tenantId)),
        ],
        'user_password' => 'nullable|string|min:6|confirmed',
    ]);

    $fotoPath = null;
    if ($r->hasFile('foto')) {
        $fotoPath = $r->file('foto')->store('drivers', 'public');
    }

    $userId = null;
    $creds  = null;

    $createUser = (bool)($data['create_user'] ?? false);
    if ($createUser) {
        if (empty($data['user_email'])) {
            return back()->withErrors(['user_email'=>'Email requerido para crear usuario.'])->withInput();
        }

        $plain = $data['user_password'] ?? \Illuminate\Support\Str::password(10);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['user_email'],
            'password'  => \Illuminate\Support\Facades\Hash::make($plain),
            'tenant_id' => $tenantId,
            'is_admin'  => 0,
            // is_dispatcher default false / 0 según tu schema
        ]);

        $userId = $user->id;
        $creds  = ['email' => $user->email, 'password' => $plain];
    }

    $id = DB::table('drivers')->insertGetId([
        'tenant_id'   => $tenantId,
        'user_id'     => $userId,
        'name'        => $data['name'],
        'phone'       => $data['phone'] ?? null,
        'email'       => $data['email'] ?? null, // contacto
        'document_id' => $data['document_id'] ?? null,
        'status'      => 'offline',
        'foto_path'   => $fotoPath,
        'active'      => (int)($data['active'] ?? 1),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $redirect = redirect()->route('drivers.show', ['id'=>$id])->with('ok','Conductor creado.');

    // Mostrar credencial SOLO una vez (flash)
    if ($creds) {
        $redirect->with('driver_creds', $creds);
    }

    return $redirect;
}


   public function show(int $id)
{
    $tenantId = $this->tenantId();

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

    // ===== Documentos del conductor (licencia / INE / selfie / foto opcional) =====
    $driverDocs = DB::table('driver_documents')
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $id)
        ->orderByDesc('id')
        ->get();

    // Mapa de tipos y requeridos (coinciden con DriverDocsController)
    $driverDocTypesMap = [
        'licencia'       => 'Licencia de conducir',
        'ine'            => 'INE / identificación oficial',
        'selfie'         => 'Selfie con identificación',
        'foto_conductor' => 'Foto del conductor (opcional)',
    ];

    // Para verificación consideramos obligatorios estos 3:
    $driverRequiredTypes = ['licencia','ine','selfie'];
 $linkedUser = null;
    if (!empty($driver->user_id)) {
        $linkedUser = User::where('tenant_id', $tenantId)
            ->where('id', $driver->user_id)
            ->first();
    }
    return view('admin.drivers.show', compact(
        'driver',
        'currentAssignment',
        'assignments',
        'vehiclesForSelect',
        'driverDocs',
        'driverDocTypesMap',
        'driverRequiredTypes',
         'linkedUser'
    ));
}


    public function edit(int $id)
    {
               $tenantId = $this->tenantId();


        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$driver, 404);

        return view('admin.drivers.edit', compact('driver'));
    }

public function update(Request $r, int $id)
{
    $tenantId = $this->tenantId();

    $driver = DB::table('drivers')
        ->where('tenant_id',$tenantId)
        ->where('id',$id)
        ->first();
    abort_if(!$driver, 404);

    $data = $r->validate([
        'name'        => 'required|string|max:120',
        'phone'       => 'nullable|string|max:30',
        'email'       => 'nullable|email|max:120',   // contacto
        'document_id' => 'nullable|string|max:60',
        'active'      => 'nullable|boolean',
        'foto'        => 'nullable|image|max:2048',

        // Cuenta (usuario)
        'create_user'    => 'nullable|boolean',
        'user_email'     => [
            'nullable','email','max:120',
            Rule::unique('users','email')
                ->where(fn($q)=>$q->where('tenant_id',$tenantId))
                ->ignore($driver->user_id),
        ],
        'change_password'=> 'nullable|boolean',
        'new_password'   => 'nullable|string|min:6|confirmed',
        'user_password'  => 'nullable|string|min:6|confirmed', // si se crea nuevo usuario
    ]);

    // Foto (reemplazo seguro)
    $fotoPath = $driver->foto_path;
    if ($r->hasFile('foto')) {
        if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
            Storage::disk('public')->delete($fotoPath);
        }
        $fotoPath = $r->file('foto')->store('drivers', 'public');
    }

    $userId = $driver->user_id ?: null;

    // 1) Si hay user existente => actualizarlo
    if ($userId) {
        $user = User::where('id',$userId)->where('tenant_id',$tenantId)->first();
        if ($user) {
            $user->name = $data['name'];
            if (!empty($data['user_email'])) {
                $user->email = $data['user_email'];
            }
            if (!empty($data['change_password']) && !empty($data['new_password'])) {
                $user->password = Hash::make($data['new_password']);

                // opcional: revocar tokens para forzar re-login
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->delete();
            }
            $user->save();
        }
    } else {
        // 2) Si no hay user y lo piden => crearlo
        $createUser = (bool)($data['create_user'] ?? false);
        if ($createUser) {
            if (empty($data['user_email'])) {
                return back()->withErrors(['user_email'=>'Email requerido para crear usuario.'])->withInput();
            }
            $plain = $data['user_password'] ?? Str::password(10);

            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['user_email'],
                'password'  => Hash::make($plain),
                'tenant_id' => $tenantId,
                'is_admin'  => 0,
            ]);

            $userId = $user->id;

            // mostrar password solo una vez
            session()->flash('driver_creds', ['email'=>$user->email,'password'=>$plain]);
        }
    }

    DB::table('drivers')
        ->where('tenant_id',$tenantId)
        ->where('id',$id)
        ->update([
            'user_id'     => $userId,
            'name'        => $data['name'],
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null, // contacto
            'document_id' => $data['document_id'] ?? null,
            'active'      => (int)($data['active'] ?? 1),
            'foto_path'   => $fotoPath,
            'updated_at'  => now(),
        ]);

    return redirect()->route('drivers.show',['id'=>$id])->with('ok','Conductor actualizado.');
}



public function resetPassword(Request $r, int $id)
{
    $tenantId = $this->tenantId();

    $driver = DB::table('drivers')
        ->where('tenant_id',$tenantId)
        ->where('id',$id)
        ->first();
    abort_if(!$driver, 404);

    if (empty($driver->user_id)) {
        return back()->withErrors(['user'=>'Este conductor no tiene usuario vinculado.']);
    }

    $user = User::where('tenant_id',$tenantId)->where('id',$driver->user_id)->firstOrFail();

    $plain = Str::password(10);
    $user->password = Hash::make($plain);
    $user->save();

    // Revocar tokens (recomendado)
    DB::table('personal_access_tokens')
        ->where('tokenable_type', User::class)
        ->where('tokenable_id', $user->id)
        ->delete();

    return back()
        ->with('ok', 'Contraseña restablecida. Se cerraron sesiones activas del conductor.')
        ->with('driver_creds', ['email'=>$user->email,'password'=>$plain]);
}


    public function destroy(int $id)
    {
               $tenantId = $this->tenantId();


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
               $tenantId = $this->tenantId();


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
               $tenantId = $this->tenantId();


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

        return back()->with('ok','Asignación cerrada.');
    }
}
