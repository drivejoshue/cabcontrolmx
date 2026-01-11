<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class TenantConsoleUserController extends Controller
{
    private function assertUserBelongs(Tenant $tenant, User $user): void
    {
        abort_unless((int)$user->tenant_id === (int)$tenant->id, 404);
    }

    public function index(Request $request, Tenant $tenant)
    {
        $q = trim((string)$request->input('q',''));
        $role = $request->input('role',''); // admin|sysadmin|user|all
        $verified = $request->input('verified',''); // yes|no|all

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($qq) use ($q) {
                    $qq->where('name','like',"%{$q}%")
                       ->orWhere('email','like',"%{$q}%");
                });
            })
            ->when($role !== '' && $role !== 'all', function ($qb) use ($role) {
                if ($role === 'sysadmin') {
                    $qb->where('is_sysadmin', true);
                } elseif ($role === 'admin') {
                    $qb->where('is_admin', true)->where('is_sysadmin', false);
                } elseif ($role === 'user') {
                    $qb->where(function ($qq) {
                        $qq->whereNull('is_admin')->orWhere('is_admin', false);
                    })->where(function ($qq) {
                        $qq->whereNull('is_sysadmin')->orWhere('is_sysadmin', false);
                    });
                }
            })
            ->when($verified !== '' && $verified !== 'all', function ($qb) use ($verified) {
                if ($verified === 'yes') $qb->whereNotNull('email_verified_at');
                if ($verified === 'no')  $qb->whereNull('email_verified_at');
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->appends(compact('q','role','verified'));

        return view('sysadmin.tenants.billing.users.index', compact('tenant','users','q','role','verified'));
    }

    public function show(Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);
        return view('sysadmin.tenants.billing.users.show', compact('tenant','user'));
    }

    public function verifyEmail(Request $request, Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);

        $user->email_verified_at = now();
        $user->save();

        Log::info('SYSADMIN_USER_VERIFY_EMAIL', ['tenant_id'=>$tenant->id, 'user_id'=>$user->id, 'email'=>$user->email]);

        return back()->with('ok', "Email marcado como verificado para {$user->email}.");
    }

    public function unverifyEmail(Request $request, Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);

        $user->email_verified_at = null;
        $user->save();

        Log::warning('SYSADMIN_USER_UNVERIFY_EMAIL', ['tenant_id'=>$tenant->id, 'user_id'=>$user->id, 'email'=>$user->email]);

        return back()->with('ok', "Email marcado como NO verificado para {$user->email}.");
    }

    public function setPassword(Request $request, Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);

        $data = $request->validate([
            'password' => ['required','string','min:8','max:120'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        Log::warning('SYSADMIN_USER_SET_PASSWORD', ['tenant_id'=>$tenant->id, 'user_id'=>$user->id, 'email'=>$user->email]);

        return back()->with('ok', "Password actualizado para {$user->email}.");
    }

    public function sendResetLink(Request $request, Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);

        // Nota: requiere que tengas mail config correcto; si no, fallará.
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('SYSADMIN_USER_SEND_RESET_FAIL', [
                'tenant_id'=>$tenant->id,
                'user_id'=>$user->id,
                'email'=>$user->email,
                'status'=>$status,
            ]);

            return back()->withErrors(['reset' => "No se pudo enviar el enlace: {$status}"]);
        }

        Log::info('SYSADMIN_USER_SEND_RESET_OK', ['tenant_id'=>$tenant->id, 'user_id'=>$user->id, 'email'=>$user->email]);

        return back()->with('ok', "Enlace de recuperación enviado a {$user->email}.");
    }

    public function revokeTokens(Request $request, Tenant $tenant, User $user)
    {
        $this->assertUserBelongs($tenant, $user);

        // Requiere Sanctum (HasApiTokens). Si no existe relación tokens(), no revienta: lo controlamos.
        try {
            $count = method_exists($user, 'tokens') ? $user->tokens()->delete() : 0;

            Log::warning('SYSADMIN_USER_REVOKE_TOKENS', [
                'tenant_id'=>$tenant->id,
                'user_id'=>$user->id,
                'email'=>$user->email,
                'deleted_tokens'=>$count,
            ]);

            return back()->with('ok', "Tokens revocados: {$count}");
        } catch (\Throwable $e) {
            Log::error('SYSADMIN_USER_REVOKE_TOKENS_FAIL', [
                'tenant_id'=>$tenant->id,
                'user_id'=>$user->id,
                'email'=>$user->email,
                'e'=>$e->getMessage(),
            ]);
            return back()->withErrors(['tokens' => 'No se pudieron revocar tokens: '.$e->getMessage()]);
        }
    }
}
