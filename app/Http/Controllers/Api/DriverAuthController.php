<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverAuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['message'=>'Credenciales inválidas'], 401);
        }

        /** @var \App\Models\User $user */
        $user = $r->user();
        // Debe estar vinculado a un driver (user->driver_id o en tabla drivers.user_id)
        $driver = DB::table('drivers')->where('user_id',$user->id)->first();
        if (!$driver) {
            return response()->json(['message'=>'Usuario no vinculado a conductor'], 403);
        }

        $token = $user->createToken('driver-app')->plainTextToken;
        return response()->json([
            'token'   => $token,
            'driver'  => ['id'=>$driver->id,'name'=>$driver->name,'phone'=>$driver->phone],
            'tenant'  => $user->tenant_id ?? 1,
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['ok'=>true]);
    }

    public function me(Request $r)
    {
        $user = $r->user();
        $driver = DB::table('drivers')->where('user_id',$user->id)->first();
        return response()->json(['user'=>$user,'driver'=>$driver]);
    }
}
