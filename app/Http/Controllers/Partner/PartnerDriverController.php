<?php

namespace App\Http\Controllers\Partner;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerDriverController extends BasePartnerController
{
    private function normTrim(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        return ($v === '') ? null : $v;
    }

    private function findDriverOr404(int $tenantId, int $partnerId, int $id)
    {
        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->first();

        abort_if(!$driver, 404);
        return $driver;
    }

    public function index(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();
        $q = trim($r->get('q', ''));

        $drivers = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
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

        return view('partner.drivers.index', compact('drivers', 'q'));
    }

    public function create()
    {
        return view('partner.drivers.create');
    }

    public function store(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $data = $r->validate([
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'email'       => [
                'nullable', 'email', 'max:120',
                Rule::unique('drivers', 'email')->where(fn($q) => $q->where('tenant_id', $tenantId)),
            ],
            'document_id' => ['nullable', 'string', 'max:60'],
            'active'      => ['nullable', 'boolean'],
            'foto'        => ['nullable', 'image', 'max:2048'],

            // por defecto: crear usuario para app
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
            return DB::transaction(function () use ($tenantId, $partnerId, $data, $fotoPath) {

                $userId = null;
                $creds  = null;

                $createUser = (bool)($data['create_user'] ?? false);
                if ($createUser) {
                    $plain = $data['user_password'] ?? Str::password(10);

                    $u = User::create([
                        'tenant_id' => $tenantId,
                        'name'      => $data['name'],
                        'email'     => $data['user_email'],
                        'password'  => Hash::make($plain),

                        'role' => UserRole::DRIVER,

                        'active'         => 1,
                        'deactivated_at' => null,

                        'is_admin'      => 0,
                        'is_dispatcher' => 0,
                        'is_sysadmin'   => 0,
                        'email_verified_at' => null,
                    ]);

                    $userId = $u->id;
                    $creds  = ['email' => $u->email, 'password' => $plain];
                }

                $id = DB::table('drivers')->insertGetId([
                    'tenant_id' => $tenantId,

                    'partner_id'             => $partnerId,
                    'recruited_by_partner_id'=> $partnerId,
                    'partner_assigned_at'    => now(),
                    'partner_left_at'        => null,

                    'user_id'     => $userId,
                    'name'        => $data['name'],
                    'phone'       => $this->normTrim($data['phone'] ?? null),
                    'email'       => $this->normTrim($data['email'] ?? null),
                    'document_id' => $this->normTrim($data['document_id'] ?? null),
                    'status'      => 'offline',
                    'foto_path'   => $fotoPath,
                    'active'      => (int)($data['active'] ?? 1),

                    'verification_status' => 'pending',
                    'verification_notes'  => null,
                    'verified_by'         => null,
                    'verified_at'         => null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ✅ Siguiente paso obligatorio: documentos
                $redirect = redirect()
                    ->route('partner.drivers.documents.index', ['id' => $id])
                    ->with('ok', 'Conductor creado. Siguiente paso: subir documentos para verificación.');

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
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

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

        // ✅ Solo vehículos del partner
        $vehiclesForSelect = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->whereIn('active', [0,1]) 
            ->orderBy('economico')
            ->select('id', 'economico', 'brand', 'model', 'plate')
            ->get();

        // Docs (para badges/resumen si tu vista los usa)
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

        // User vinculado (mismatch-safe)
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

        return view('partner.drivers.show', compact(
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
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

        return view('partner.drivers.edit', compact('driver'));
    }

    public function update(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

        // user vinculado (mismatch-safe)
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
            'email'       => [
                'nullable', 'email', 'max:120',
                Rule::unique('drivers', 'email')
                    ->ignore($id)
                    ->where(fn($q) => $q->where('tenant_id', $tenantId)),
            ],
            'document_id' => ['nullable', 'string', 'max:60'],
            'active'      => ['nullable', 'boolean'],
            'foto'        => ['nullable', 'image', 'max:2048'],

            // Cuenta
            'create_user'     => ['nullable', 'boolean'],
            'user_email'      => [
                'nullable', 'email', 'max:120',
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

        return DB::transaction(function () use (
            $tenantId, $partnerId, $driver, $existingUser, $existingUserMismatch, $data, $fotoPath, $id
        ) {
            $userId = $existingUser?->id;

            // mismatch: no tocar user ajeno
            if ($existingUserMismatch) {
                $userId = null;
            }

            // 1) Actualizar user existente (si es del tenant)
            if ($existingUser) {
                $existingUser->name = $data['name'];

                if (!empty($data['user_email'])) {
                    $existingUser->email = $data['user_email'];
                }

                if (!empty($data['change_password']) && !empty($data['new_password'])) {
                    $existingUser->password = Hash::make($data['new_password']);

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
                    return back()
                        ->withErrors(['user_email' => 'Email requerido para crear usuario.'])
                        ->withInput();
                }

                $plain = $data['user_password'] ?? Str::password(10);

                $u = User::create([
                    'tenant_id' => $tenantId,
                    'name'      => $data['name'],
                    'email'     => $data['user_email'],
                    'password'  => Hash::make($plain),

                    'role' => UserRole::DRIVER,

                    'active'         => 1,
                    'deactivated_at' => null,

                    'is_admin'      => 0,
                    'is_dispatcher' => 0,
                    'is_sysadmin'   => 0,
                    'email_verified_at' => null,
                ]);

                $userId = $u->id;
                session()->flash('driver_creds', ['email' => $u->email, 'password' => $plain]);
            }

            // Driver (manteniendo partner_id intacto)
            DB::table('drivers')
                ->where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
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

            return redirect()->route('partner.drivers.show', ['id' => $id])->with('ok', $msg);
        });
    }

    public function resetPassword(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

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
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

        DB::transaction(function () use ($driver) {

            DB::table('drivers')
                ->where('id', $driver->id)
                ->update([
                    'active'     => 0,
                    'status'     => 'offline',
                    'updated_at' => now(),
                ]);

            if (!empty($driver->user_id)) {
                DB::table('users')
                    ->where('id', $driver->user_id)
                    ->update([
                        'active'         => 0,
                        'deactivated_at' => now(),
                        'updated_at'     => now(),
                    ]);

                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $driver->user_id)
                    ->delete();
            }

            DB::table('driver_vehicle_assignments')
                ->where('tenant_id', $driver->tenant_id)
                ->where('driver_id', $driver->id)
                ->whereNull('end_at')
                ->update(['end_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('partner.drivers.index')->with('ok', 'Conductor desactivado.');
    }

    public function assignVehicle(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $data = $r->validate([
            'vehicle_id'      => ['required', 'integer'],
            'start_at'        => ['nullable', 'date'],
            'note'            => ['nullable', 'string', 'max:255'],
            'close_conflicts' => ['nullable', 'boolean'],
        ]);

        $driver = $this->findDriverOr404($tenantId, $partnerId, $id);

        // ✅ Solo vehículos del partner
        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', $data['vehicle_id'])
            ->first();

        if (!$vehicle) {
            return back()->withErrors(['vehicle_id' => 'Vehículo inválido o no pertenece a tu cuenta.'])->withInput();
        }

       $startAt = $data['start_at'] ?? now();
        $close   = $r->boolean('close_conflicts', true);

        DB::beginTransaction();
        try {
            if ($close) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);

                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_id', $vehicle->id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $id,
                'vehicle_id' => $vehicle->id,
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

        return back()->with('ok', 'Vehículo asignado.');
    }

   public function closeAssignment(int $assignmentId)
{
    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    $a = DB::table('driver_vehicle_assignments as a')
        ->join('vehicles as v', function ($j) use ($tenantId, $partnerId) {
            $j->on('v.id','=','a.vehicle_id')
              ->where('v.tenant_id','=',$tenantId)
              ->where('v.partner_id','=',$partnerId);
        })
        ->where('a.tenant_id', $tenantId)
        ->where('a.id', $assignmentId)
        ->select('a.*')
        ->first();

    abort_if(!$a, 404);

    DB::table('driver_vehicle_assignments')
        ->where('tenant_id', $tenantId)
        ->where('id', $assignmentId)
        ->update(['end_at' => now(), 'updated_at' => now()]);

    return back()->with('ok', 'Asignación cerrada.');
}

}
