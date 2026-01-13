<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;


class DriverController extends Controller
{
    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int) $tid;
    }

    private function normTrim(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        return ($v === '') ? null : $v;
    }

    public function index(Request $r)
    {
        $tenantId = $this->tenantId();
        $q = trim($r->get('q', ''));

        $drivers = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%$q%")
                      ->orWhere('phone', 'like', "%$q%")
                      ->orWhere('email', 'like', "%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.drivers.index', compact('drivers', 'q'));
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

    public function store(Request $r)
    {
        
    $tenantId = $this->tenantId();

    $data = $r->validate([
        'name'        => ['required', 'string', 'max:120'],
        'phone'       => ['nullable', 'string', 'max:30'],
        'email'       => ['nullable', 'email', 'max:120'],
        'document_id' => ['nullable', 'string', 'max:60'],
        'active'      => ['nullable', 'boolean'],
        'foto'        => ['nullable', 'image', 'max:2048'],
'role' => ['sometimes', Rule::in(['driver','admin','dispatcher','sysadmin'])],

        'create_user'   => ['nullable', 'boolean'],
        'user_email'    => [
            Rule::requiredIf(fn () => (bool)$r->input('create_user')),
            'nullable', 'email', 'max:120',
            'unique:users,email',
        ],
        'user_password' => ['nullable', 'string', 'min:6', 'confirmed'],
    ]);

    
        $fotoPath = null;
        if ($r->hasFile('foto')) {
            $fotoPath = $r->file('foto')->store('drivers', 'public');
        }

        try {
            return DB::transaction(function () use ($tenantId, $data, $fotoPath) {

                $userId = null;
                $creds  = null;

                $createUser = (bool)($data['create_user'] ?? false);
                if ($createUser) {
                    $plain = $data['user_password'] ?? Str::password(10);

                    $user = User::create([
                    'tenant_id'     => $tenantId,
                    'name'          => $data['name'],
                    'email'         => $data['user_email'],
                    'password'      => Hash::make($plain),

                    'role'      => UserRole::DRIVER, // recomendado con cast Enum

                    'active'        => 1,
                    'deactivated_at'=> null,

                    'is_admin'      => 0,
                    'is_dispatcher' => 0,
                    'is_sysadmin'   => 0,
                    'email_verified_at' => null,
                ]);


                    $userId = $user->id;
                    $creds  = ['email' => $user->email, 'password' => $plain];
                }

                $id = DB::table('drivers')->insertGetId([
                    'tenant_id'   => $tenantId,
                    'user_id'     => $userId,
                    'name'        => $data['name'],
                    'phone'       => $this->normTrim($data['phone'] ?? null),
                    'email'       => $this->normTrim($data['email'] ?? null),
                    'document_id' => $this->normTrim($data['document_id'] ?? null),
                    'status'      => 'offline',
                    'foto_path'   => $fotoPath,
                    'active'      => (int)($data['active'] ?? 1),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $redirect = redirect()->route('admin.drivers.show', ['id' => $id])->with('ok', 'Conductor creado.');
                if ($creds) $redirect->with('driver_creds', $creds);

                return $redirect;
            });
        } catch (\Throwable $e) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            throw $e;
        }
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        $currentAssignment = DB::table('driver_vehicle_assignments as a')
            ->join('vehicles as v', 'v.id', '=', 'a.vehicle_id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.driver_id', $id)
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

        $assignments = DB::table('driver_vehicle_assignments as a')
            ->join('vehicles as v', 'v.id', '=', 'a.vehicle_id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.driver_id', $id)
            ->orderByDesc('a.start_at')
            ->select([
                'a.start_at', 'a.end_at',
                'v.id', 'v.economico', 'v.plate', 'v.brand', 'v.model',
            ])
            ->get();

        $vehiclesForSelect = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
            ->orderBy('economico')
            ->select('id', 'economico', 'brand', 'model', 'plate')
            ->get();

        $driverDocs = DB::table('driver_documents')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $id)
            ->orderByDesc('id')
            ->get();

        $driverDocTypesMap = [
            'licencia'       => 'Licencia de conducir',
            'ine'            => 'INE / identificación oficial',
            'selfie'         => 'Selfie con identificación',
            'foto_conductor' => 'Foto del conductor (opcional)',
        ];

        $driverRequiredTypes = ['licencia', 'ine', 'selfie'];

        $linkedUser = null;
        $linkedUserMismatch = false;

        if (!empty($driver->user_id)) {
            $u = User::where('id', $driver->user_id)->first();
            if (!$u) {
                $linkedUserMismatch = true;
            } elseif ((int)$u->tenant_id !== (int)$tenantId) {
                $linkedUserMismatch = true;
            } else {
                $linkedUser = $u;
            }
        }

        return view('admin.drivers.show', compact(
            'driver',
            'currentAssignment',
            'assignments',
            'vehiclesForSelect',
            'driverDocs',
            'driverDocTypesMap',
            'driverRequiredTypes',
            'linkedUser',
            'linkedUserMismatch'
        ));
    }

    public function edit(int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        return view('admin.drivers.edit', compact('driver'));
    }

    public function update(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        // Detectar corrupción: user_id apunta a otro tenant
        $existingUser = null;
        $existingUserMismatch = false;
        if (!empty($driver->user_id)) {
            $u = User::where('id', $driver->user_id)->first();
            if (!$u || (int)$u->tenant_id !== (int)$tenantId) {
                $existingUserMismatch = true;
            } else {
                $existingUser = $u;
            }
        }

        $data = $r->validate([
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:60'],
            'active'      => ['nullable', 'boolean'],
            'foto'        => ['nullable', 'image', 'max:2048'],

            // Cuenta
            'create_user'     => ['nullable', 'boolean'],
            'user_email'      => [
                'nullable', 'email', 'max:120',
                // ÚNICO GLOBAL; si ya hay user del mismo driver válido, lo ignoramos
                Rule::unique('users', 'email')->ignore($existingUser?->id),
            ],
            'change_password' => ['nullable', 'boolean'],
            'new_password'    => ['nullable', 'string', 'min:6', 'confirmed'],
            'user_password'   => ['nullable', 'string', 'min:6', 'confirmed'], // para crear
        ]);

        // Foto
        $fotoPath = $driver->foto_path;
        if ($r->hasFile('foto')) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            $fotoPath = $r->file('foto')->store('drivers', 'public');
        }

        try {
            return DB::transaction(function () use (
                $tenantId, $driver, $existingUser, $existingUserMismatch, $data, $fotoPath, $id
            ) {
                $userId = $existingUser?->id;

                // Si hay mismatch, NO tocar ese user ajeno; tratamos como si no hubiera user
                if ($existingUserMismatch) {
                    $userId = null;
                }

                // 1) Actualizar user existente (solo si es del tenant)
                if ($existingUser) {
                    $existingUser->name = $data['name'];

                    if (!empty($data['user_email'])) {
                        $existingUser->email = $data['user_email'];
                    }

                    if (!empty($data['change_password']) && !empty($data['new_password'])) {
                        $existingUser->password = Hash::make($data['new_password']);

                        // revocar tokens para re-login
                        DB::table('personal_access_tokens')
                            ->where('tokenable_type', User::class)
                            ->where('tokenable_id', $existingUser->id)
                            ->delete();
                    }

                    $existingUser->save();
                    $userId = $existingUser->id;
                }

                // 2) Crear user si no existe y lo piden
                $createUser = (bool)($data['create_user'] ?? false);
                if (!$userId && $createUser) {
                    if (empty($data['user_email'])) {
                        return back()->withErrors(['user_email' => 'Email requerido para crear usuario.'])->withInput();
                    }

                    $plain = $data['user_password'] ?? Str::password(10);

                    $u = User::create([
                        'tenant_id'     => $tenantId,
                        'name'          => $data['name'],
                        'email'         => $data['user_email'],
                        'password'      => Hash::make($plain),
                        'is_admin'      => 0,
                        'is_dispatcher' => 0,
                        'is_sysadmin'   => 0,
                        'email_verified_at' => null,
                    ]);

                    $userId = $u->id;
                    session()->flash('driver_creds', ['email' => $u->email, 'password' => $plain]);
                }

                DB::table('drivers')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $id)
                    ->update([
                        'user_id'     => $userId,
                        'name'        => $data['name'],
                        'phone'       => $this->normTrim($data['phone'] ?? null),
                        'email'       => $this->normTrim($data['email'] ?? null),
                        'document_id' => $this->normTrim($data['document_id'] ?? null),
                        'active'      => (int)($data['active'] ?? 1),
                        'foto_path'   => $fotoPath,
                        'updated_at'  => now(),
                    ]);

                $msg = $existingUserMismatch
                    ? 'Conductor actualizado. Nota: tenía un user_id apuntando a otro tenant; se reemplazó/normalizó.'
                    : 'Conductor actualizado.';

                return redirect()->route('admin.drivers.show', ['id' => $id])->with('ok', $msg);
            });
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function resetPassword(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        if (empty($driver->user_id)) {
            return back()->withErrors(['user' => 'Este conductor no tiene usuario vinculado.']);
        }

        $user = User::where('id', $driver->user_id)->where('tenant_id', $tenantId)->first();
        if (!$user) {
            return back()->withErrors(['user' => 'Usuario vinculado inválido (posible mismatch de tenant).']);
        }

        $plain = Str::password(10);
        $user->password = Hash::make($plain);
        $user->save();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        return back()
            ->with('ok', 'Contraseña restablecida. Se cerraron sesiones activas del conductor.')
            ->with('driver_creds', ['email' => $user->email, 'password' => $plain]);
    }

    public function destroy(int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        DB::table('drivers')
            ->where('id', $id)
            ->update(['active' => 0, 'updated_at' => now()]);

        return redirect()->route('admin.drivers.index')->with('ok', 'Conductor desactivado.');
    }

    public function assignVehicle(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $data = $r->validate([
            'vehicle_id'      => ['required', 'integer'],
            'start_at'        => ['nullable', 'date'],
            'note'            => ['nullable', 'string', 'max:255'],
            'close_conflicts' => ['nullable', 'boolean'],
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);

        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $data['vehicle_id'])
            ->first();

        if (!$vehicle) {
            return back()->withErrors(['vehicle_id' => 'Vehículo inválido'])->withInput();
        }

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if (!empty($data['close_conflicts'])) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);

                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_id', $data['vehicle_id'])
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $id,
                'vehicle_id' => $data['vehicle_id'],
                'start_at'   => $startAt,
                'end_at'     => null,
                'note'       => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['assign' => 'No se pudo asignar: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.drivers.show', ['id' => $id])->with('ok', 'Vehículo asignado.');
    }

    public function closeAssignment(int $assignmentId)
    {
        $tenantId = $this->tenantId();

        $a = DB::table('driver_vehicle_assignments')
            ->where('tenant_id', $tenantId)
            ->where('id', $assignmentId)
            ->first();

        abort_if(!$a, 404);

        DB::table('driver_vehicle_assignments')
            ->where('id', $assignmentId)
            ->update([
                'end_at'     => now(),
                'updated_at' => now(),
            ]);

        return back()->with('ok', 'Asignación cerrada.');
    }
}
