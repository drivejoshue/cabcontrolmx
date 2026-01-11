<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class StaffUserController extends Controller
{
    private function tenantId(): int
    {
        return (int)(auth()->user()->tenant_id ?? 0);
    }

    private function assertSameTenant(User $user): void
    {
        if ((int)$user->tenant_id !== $this->tenantId()) abort(403, 'Usuario fuera de tu tenant.');
        if (!empty($user->is_sysadmin)) abort(403, 'No editable.');
    }

   public function index(Request $r)
{
    $tid = $this->tenantId();
    $q = trim((string)$r->get('q',''));

    $base = User::query()
        ->from('users')
        ->select('users.*')
        ->where('users.tenant_id', $tid)
        ->where('users.is_sysadmin', 0);

    if ($q !== '') {
        $base->where(function ($w) use ($q) {
            $w->where('users.name', 'like', "%{$q}%")
              ->orWhere('users.email', 'like', "%{$q}%");
        });
    }

    $admins = (clone $base)
        ->where('users.active', 1)
        ->where('users.role', 'admin')
        ->orderBy('users.name')
        ->get();

    $dispatchers = (clone $base)
        ->where('users.active', 1)
        ->where('users.role', 'dispatcher')
        ->orderBy('users.name')
        ->get();

    $drivers = (clone $base)
        ->where('users.active', 1)
        ->where('users.role', 'driver')
        ->leftJoin('drivers as d', function ($j) use ($tid) {
            $j->on('d.user_id', '=', 'users.id')
              ->where('d.tenant_id', '=', $tid);
        })
        ->addSelect([
            'd.id as driver_id',
            'd.name as driver_name',
            'd.status as driver_status',
            'd.active as driver_active',
        ])
        ->orderBy('users.name')
        ->get();

    $inactive = (clone $base)
        ->where('users.active', 0)
        ->orderBy('users.name')
        ->get();

    return view('admin.users.index', compact('q','admins','dispatchers','drivers','inactive'));
}


    public function create()
    {
        return view('admin.users.create');
    }






    // Solo crea STAFF (admin/dispatcher). Drivers se crean en DriverController.
    public function store(Request $r)
    {
        $tid = $this->tenantId();

        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'role'     => ['required','in:admin,dispatcher'],
            'password' => ['nullable','string','min:8'],
        ]);

        $plain = $data['password'] ?: str()->random(12);

        $role = $data['role'];
        $isAdmin = $role === 'admin';
        $isDispatcher = $role === 'dispatcher';

        User::create([
            'tenant_id'         => $tid,
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($plain),
            'role'              => $role,
            'active'            => 1,
            'deactivated_at'    => null,

            // compat
            'is_admin'          => $isAdmin ? 1 : 0,
            'is_dispatcher'     => $isDispatcher ? 1 : 0,
            'is_sysadmin'       => 0,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado. Password temporal: '.$plain);
    }

    public function edit(User $user)
    {
        $this->assertSameTenant($user);
        return view('admin.users.edit', compact('user'));
    }

    // Rol NO se modifica aquí. Solo name/email.
     public function update(Request $r, User $user)
{
    $this->assertSameTenant($user);

    $data = $r->validate([
        'name'  => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
    ]);

    $user->name  = $data['name'];
    $user->email = $data['email'];
    $user->save();

    return redirect()
        ->route('admin.users.index')
        ->with('success', 'Usuario actualizado.');
}

    public function setPassword(Request $r, User $user)
    {
        $this->assertSameTenant($user);

        $data = $r->validate([
            'password' => ['required','string','min:8','confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        // Revocar tokens para forzar re-login
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        return back()->with('success','Password actualizado. Sesiones revocadas.');
    }

    public function sendResetLink(Request $r, User $user)
    {
        $this->assertSameTenant($user);

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
            return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'warning', __($status));
        } catch (\Throwable $e) {
            return back()->with('warning','No se pudo enviar el correo de reset. Verifica MAIL.');
        }
    }

    private function forbidSelf(User $user): void
{
    if ((int)auth()->id() === (int)$user->id) {
        abort(422, 'No puedes desactivarte a ti mismo.');
    }
}

private function forbidLastAdmin(User $user): void
{
    // Si intentan desactivar un admin, asegura que exista al menos otro admin activo.
    if ($user->role !== 'admin') return;

    $tid = $this->tenantId();

    $activeAdmins = User::query()
        ->where('tenant_id', $tid)
        ->where('is_sysadmin', 0)
        ->where('active', 1)
        ->where('role', 'admin')
        ->count();

    if ($activeAdmins <= 1) {
        abort(422, 'No puedes desactivar al último Admin del tenant.');
    }
}


  public function deactivate(Request $r, User $user)
{
    $this->assertSameTenant($user);
    $this->forbidSelf($user);
    $this->forbidLastAdmin($user);

    if ((int)$user->active === 0) {
        return back()->with('warning', 'El usuario ya está desactivado.');
    }

    DB::transaction(function () use ($user, $r) {
        $user->active = 0;
        $user->deactivated_at = now();
        $user->save();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        // Si todavía NO tienes tabla de auditoría, comenta este bloque (no invento migraciones aquí)
        // DB::table('user_archive_events')->insert([...]);
    });

    return back()->with('success','Usuario desactivado.');
}


    public function reactivate(User $user)
    {
        $this->assertSameTenant($user);

        if ((int)$user->active === 1) {
            return back()->with('warning', 'El usuario ya está activo.');
        }

        DB::transaction(function () use ($user) {
            $user->active = 1;
            $user->deactivated_at = null;
            $user->save();

            DB::table('user_archive_events')->insert([
                'tenant_id'     => $user->tenant_id,
                'user_id'       => $user->id,
                'action'        => 'reactivated',
                'performed_by'  => auth()->id(),
                'reason'        => null,
                'snapshot'      => json_encode([
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'    => now(),
            ]);
        });

        return back()->with('success','Usuario reactivado.');
    }
}
