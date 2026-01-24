<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email'          => 'required|email',
            'password'       => 'required|string',
            'single_session' => 'sometimes|boolean',
        ]);

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['ok' => false, 'message' => 'Credenciales inválidas'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (property_exists($user, 'active') && (int)($user->active ?? 1) === 0) {
            Auth::logout();
            return response()->json([
                'ok' => false,
                'message' => 'Usuario desactivado. Contacta a tu administrador.'
            ], 403);
        }

        // ¿Es driver? (proteger cross-tenant)
        $driver = DB::table('drivers')
            ->where('user_id', $user->id)
            ->when(!empty($user->tenant_id), fn($q) => $q->where('tenant_id', $user->tenant_id))
            ->first();

        if ($driver) {
            // Driver app: single-session
            $user->tokens()->whereIn('name', ['api-token', 'driver-app'])->delete();

            $token = $user->createToken('driver-app')->plainTextToken;

            return response()->json([
                'ok'    => true,
                'token' => 'Bearer ' . $token,
                'user'  => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id ?? null,
                    'role' => (string)($user->role ?? null),
                    'is_admin' => (bool)($user->is_admin ?? false),
                    'is_dispatcher' => (bool)($user->is_dispatcher ?? false),
                    'is_sysadmin' => (bool)($user->is_sysadmin ?? false),
                    'default_partner_id' => $user->default_partner_id ?? null,
                    'web_home' => $user->preferredWebHomePath(),
                ],
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                ],
            ]);
        }

        // No-driver
        if (!empty($data['single_session'])) {
            $user->tokens()->delete();
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => 'Bearer ' . $token,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id ?? null,
                'role' => (string)($user->role ?? null),
                'is_admin' => (bool)($user->is_admin ?? false),
                'is_dispatcher' => (bool)($user->is_dispatcher ?? false),
                'is_sysadmin' => (bool)($user->is_sysadmin ?? false),
                'default_partner_id' => $user->default_partner_id ?? null,
                'web_home' => $user->preferredWebHomePath(),
            ],
        ]);
    }

    public function logout(Request $r)
    {
        $user = $r->user();
        $user?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $r)
    {
        /** @var User $user */
        $user = $r->user();

        return response()->json([
            'ok'   => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id ?? null,
                'role' => (string)($user->role ?? null),
                'is_admin' => (bool)($user->is_admin ?? false),
                'is_dispatcher' => (bool)($user->is_dispatcher ?? false),
                'is_sysadmin' => (bool)($user->is_sysadmin ?? false),
                'default_partner_id' => $user->default_partner_id ?? null,
                'web_home' => $user->preferredWebHomePath(),
            ],
        ]);
    }
}
