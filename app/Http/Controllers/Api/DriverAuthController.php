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
        $tenantId = $r->header('X-Tenant-ID') ?: ($user->tenant_id ?? 1);  // ← override por header

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
                ->orderByDesc('started_at')
                ->first();

            if ($shift && $shift->vehicle_id) {
                $vehicle = DB::table('vehicles')
                    ->where('tenant_id',$tenantId)
                    ->where('id',$shift->vehicle_id)
                    ->select('id','economico','plate','brand','model','type')
                    ->first();
            }
        }

        return response()->json([
            'ok'=>true,
            'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'tenant_id'=>$tenantId],
            'driver'=>$driver,
            'current_shift'=>$shift,
            'vehicle'=>$vehicle,
        ]);
    }

}
