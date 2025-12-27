<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class StaffUserController extends Controller
{
    private function tenantId(): int
    {
        return (int) (auth()->user()->tenant_id ?? 0);
    }

    private function assertSameTenant(User $user): void
    {
        if ((int)$user->tenant_id !== $this->tenantId()) {
            abort(403, 'Usuario fuera de tu tenant.');
        }
        if (!empty($user->is_sysadmin)) {
            abort(403, 'No editable.');
        }
    }

    private function ensureExactlyOneRole(bool $isAdmin, bool $isDispatcher): void
    {
        // exactamente uno true
        if (($isAdmin && $isDispatcher) || (!$isAdmin && !$isDispatcher)) {
            throw ValidationException::withMessages([
                'role' => 'Debes seleccionar exactamente un rol: Admin o Dispatcher.',
            ]);
        }
    }

    public function index()
    {
        $tid = $this->tenantId();

        $items = User::query()
            ->where('tenant_id', $tid)
            ->where('is_sysadmin', 0)
            ->where(function ($q) {
                $q->where('is_admin', 1)
                  ->orWhere('is_dispatcher', 1);
            })
            ->orderByDesc('is_admin')
            ->orderByDesc('is_dispatcher')
            ->orderBy('name')
            ->get();

        return view('admin.users.index', compact('items'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $r)
    {
        $tid = $this->tenantId();

        $data = $r->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'kind'     => ['required', 'in:dispatcher,admin'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $isAdmin = $data['kind'] === 'admin';
        $isDispatcher = $data['kind'] === 'dispatcher';

        // Regla estricta: exactamente un rol
        $this->ensureExactlyOneRole($isAdmin, $isDispatcher);

        $plainPassword = $data['password'] ?: str()->random(12);

        User::create([
            'tenant_id'        => $tid,
            'name'             => $data['name'],
            'email'            => $data['email'],
            'password'         => Hash::make($plainPassword),
            'is_admin'         => $isAdmin ? 1 : 0,
            'is_dispatcher'    => $isDispatcher ? 1 : 0,
            'is_sysadmin'      => 0,
            'email_verified_at'=> now(), // staff interno
        ]);

        // Si NO quieres mostrar password en flash, quita el password temporal del mensaje
        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario creado. Password temporal: '.$plainPassword);
    }

    public function edit(User $user)
    {
        $this->assertSameTenant($user);
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $r, User $user)
    {
        $this->assertSameTenant($user);

        $data = $r->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            // OJO: checkbox no manda valor si no está marcado, por eso no uses boolean aquí
            'is_admin'      => ['nullable'],
            'is_dispatcher' => ['nullable'],
        ]);

        $isAdmin = !empty($data['is_admin']);
        $isDispatcher = !empty($data['is_dispatcher']);

        // Regla estricta: exactamente un rol
        $this->ensureExactlyOneRole($isAdmin, $isDispatcher);

        // Evitar que el admin se quite su propio rol y se bloquee
        $me = Auth::user();
        if ($me && (int)$me->id === (int)$user->id && !$isAdmin) {
            throw ValidationException::withMessages([
                'is_admin' => 'No puedes quitarte el rol de Admin a ti mismo.',
            ]);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_admin = $isAdmin ? 1 : 0;
        $user->is_dispatcher = $isDispatcher ? 1 : 0;

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario actualizado.');
    }

    public function setPassword(Request $r, User $user)
    {
        $this->assertSameTenant($user);

        $data = $r->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('success', 'Password actualizado.');
    }

    public function sendResetLink(Request $r, User $user)
    {
        $this->assertSameTenant($user);

        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            return back()->with(
                $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
                __($status)
            );
        } catch (\Throwable $e) {
            // Evita 500 si no hay mail configurado
            return back()->with('warning', 'No se pudo enviar el correo de reset. Verifica la configuración de MAIL.');
        }
    }
}
