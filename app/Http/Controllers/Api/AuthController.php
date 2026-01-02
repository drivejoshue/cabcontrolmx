<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Login genérico de usuario (NO toca drivers / rides / shifts).
     * Úsalo para paneles o clientes que sólo requieran user + token.
     */
    public function login(Request $r)
    {
        $data = $r->validate([
            'email'          => 'required|email',
            'password'       => 'required|string',
            'single_session' => 'sometimes|boolean',
        ]);

        if (!Auth::attempt([
            'email'    => $data['email'],
            'password' => $data['password'],
        ])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user     = $r->user();
        $tenantId = $user->tenant_id ?? null;

        // Opcional: single-session (revoca otros tokens de este usuario)
        if (!empty($data['single_session'])) {
            $user->tokens()->delete();
        }

        // Nombre genérico para el token de API "normal"
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => 'Bearer '.$token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'tenant_id'  => $tenantId,
                'is_admin'   => (bool)($user->is_admin ?? false),
                'is_sysadmin'=> (bool)($user->is_sysadmin ?? false),
            ],
        ]);
    }

    /**
     * Logout genérico: revoca sólo el token actual.
     * No toca estados de drivers ni turnos.
     */
    public function logout(Request $r)
    {
        $user = $r->user();
        $user?->currentAccessToken()?->delete();


        
        return response()->json(['ok' => true]);
    }

    /**
     * /me genérico: sólo datos del usuario logueado.
     */
    public function me(Request $r)
    {
        $user     = $r->user();
        $tenantId = $user->tenant_id ?? null;

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'tenant_id'  => $tenantId,
                'is_admin'   => (bool)($user->is_admin ?? false),
                'is_sysadmin'=> (bool)($user->is_sysadmin ?? false),
            ],
        ]);
    }
}
