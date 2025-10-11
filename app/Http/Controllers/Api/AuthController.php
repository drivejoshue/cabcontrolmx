<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['ok'=>false,'message'=>'Credenciales inválidas'], 401);
        }

        $user = $r->user(); // ya autenticado
        $tenantId = $user->tenant_id ?? 1;

        // token (Sanctum)
        $token = $user->createToken('driver-token')->plainTextToken;

        // driver vinculado por user_id y tenant
        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        // turno abierto (si existe)
        $shift = null;
        $vehicle = null;
        if ($driver) {
            $shift = DB::table('driver_shifts')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $driver->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->first();

            if ($shift && $shift->vehicle_id) {
                $vehicle = DB::table('vehicles')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $shift->vehicle_id)
                    ->select('id','economico','plate','brand','model','type')
                    ->first();
            }
        }

        // POLÍTICA: login no abre turno → queda offline
        if ($driver && $driver->status !== 'offline') {
            DB::table('drivers')->where('id', $driver->id)->update([
                'status'     => 'offline',
                'updated_at' => now(),
            ]);
            $driver->status = 'offline';
        }

        return response()->json([
            'ok'    => true,
            'token' => 'Bearer '.$token,
            'user'  => [
                'id' => $user->id, 'name'=>$user->name,
                'tenant_id'=>$tenantId, 'email'=>$user->email,
            ],
            'driver'        => $driver,
            'current_shift' => $shift,
            'vehicle'       => $vehicle,
        ]);
    }

    public function logout(Request $r)
    {
        $user = $r->user();
        $tenantId = $user->tenant_id ?? 1;

        // Revoca solo el token actual
        $user->currentAccessToken()?->delete();

        // Poner driver offline (NO cerramos turno automáticamente)
        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('user_id',$user->id)->first();
        if ($driver) {
            DB::table('drivers')->where('id', $driver->id)->update([
                'status'     => 'offline',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok'=>true]);
    }

    public function me(Request $r)
    {
        $user = $r->user();
        $tenantId = $user->tenant_id ?? 1;

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('user_id',$user->id)
            ->first();

        $shift = null; $vehicle = null;
        if ($driver) {
            $shift = DB::table('driver_shifts')
                ->where('tenant_id',$tenantId)
                ->where('driver_id',$driver->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')->first();

            if ($shift && $shift->vehicle_id) {
                $vehicle = DB::table('vehicles')
                    ->where('tenant_id',$tenantId)
                    ->where('id',$shift->vehicle_id)
                    ->select('id','economico','plate','brand','model','type')
                    ->first();
            }
        }

        return response()->json([
            'ok'           => true,
            'user'         => ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'tenant_id'=>$tenantId],
            'driver'       => $driver,
            'current_shift'=> $shift,
            'vehicle'      => $vehicle,
        ]);
    }
}
