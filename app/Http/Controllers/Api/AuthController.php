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

    if (!Auth::attempt(['email'=>$data['email'], 'password'=>$data['password']])) {
        return response()->json(['ok'=>false,'message'=>'Credenciales inválidas'], 401);
    }

    /** @var User $user */
    $user = Auth::user();



if (property_exists($user, 'active') && (int)$user->active === 0) {
    Auth::logout();
    return response()->json([
        'ok' => false,
        'message' => 'Usuario desactivado. Contacta a tu administrador.'
    ], 403);
}



    // ¿Es driver?
    $driver = DB::table('drivers')->where('user_id', $user->id)->first();

    if ($driver) {
        // ✅ Driver app: SIEMPRE single-session (sin tablas nuevas)
        $user->tokens()->whereIn('name', ['api-token','driver-app'])->delete();


    //     $driver = DB::table('drivers')
    // ->where('user_id', $user->id)
    // ->where('tenant_id', $user->tenant_id) // <- evita cross-tenant por datos sucios
    // ->first();


        $token = $user->createToken('driver-app')->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => 'Bearer '.$token,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id ?? null,
                'is_admin' => (bool)($user->is_admin ?? false),
                'is_sysadmin' => (bool)($user->is_sysadmin ?? false),
            ],
            // opcional: devolver driver mínimo si quieres
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
            ],
        ]);
    }

    // NO driver: comportamiento actual
    if (!empty($data['single_session'])) {
        $user->tokens()->delete();
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'ok'    => true,
        'token' => 'Bearer '.$token,
        'user'  => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id ?? null,
            'is_admin' => (bool)($user->is_admin ?? false),
            'is_sysadmin' => (bool)($user->is_sysadmin ?? false),
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
