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
        'single_session' => 'sometimes|boolean',
    ]);

    if (!Auth::attempt(['email'=>$data['email'], 'password'=>$data['password']])) {
        return response()->json(['ok'=>false,'message'=>'Credenciales inválidas'], 401);
    }

    $user     = $r->user();
    $tenantId = $user->tenant_id ?? 1;

    // Opcional: single-session
    if (!empty($data['single_session'])) {
        $user->tokens()->delete();
    }

    $token = $user->createToken('driver-token')->plainTextToken;

    $driver = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->first();

    $shift = null; $vehicle = null;
    if ($driver) {
        $shift = DB::table('driver_shifts')
            ->where('tenant_id',$tenantId)->where('driver_id',$driver->id)
            ->whereNull('ended_at')->orderByDesc('started_at')->first(); // schema ok. :contentReference[oaicite:4]{index=4}

        if ($shift && $shift->vehicle_id) {
            $vehicle = DB::table('vehicles')
                ->where('tenant_id',$tenantId)
                ->where('id',$shift->vehicle_id)
                ->select('id','economico','plate','brand','model','type')
                ->first();
        }

        // Política: login ≠ abrir turno -> si no hay turno abierto, marcar offline (no toques si hay turno)
        if (!$shift && $driver->status !== 'offline') {
            DB::table('drivers')->where('id', $driver->id)->update([
                'status'     => 'offline', // enum('offline','idle','busy') en drivers. :contentReference[oaicite:5]{index=5}
                'updated_at' => now(),
            ]);
            $driver->status = 'offline';
        }
    }

    return response()->json([
        'ok'    => true,
        'token' => 'Bearer '.$token,
        'user'  => ['id'=>$user->id,'name'=>$user->name,'tenant_id'=>$tenantId,'email'=>$user->email],
        'driver'        => $driver,
        'current_shift' => $shift,
        'vehicle'       => $vehicle,
    ]);
}


   public function logout(Request $r)
{
    $user     = $r->user();
    $tenantId = $user->tenant_id ?? 1;

    // Revoca SOLO el token actual (Sanctum)
    $user->currentAccessToken()?->delete();

    // Si no hay driver vinculado, termina
    $driver = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->first();

    if (!$driver) {
        return response()->json(['ok' => true]);
    }

    // ¿Tiene ride activo?
    $hasActiveRide = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driver->id)
        ->whereIn('status', ['accepted','en_route','arrived','on_board'])
        ->exists();

    // ¿Tiene otro turno abierto?
    $hasOpenShift = DB::table('driver_shifts')
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driver->id)
        ->whereNull('ended_at')
        ->where('status', 'abierto')
        ->exists(); // driver_shifts.status enum('abierto','cerrado'). :contentReference[oaicite:2]{index=2}

    // Política: logout NO cierra turno.
    // Estados:
    // - Con ride activo -> mantener 'busy'
    // - Sin ride activo, con turno abierto -> no tocar (watchdog/finish lo marcarán)
    // - Sin ride activo y sin turno abierto -> 'offline'
    $newStatus = null;
    if ($hasActiveRide) {
        $newStatus = 'busy';  // drivers.status enum('offline','idle','busy'). :contentReference[oaicite:3]{index=3}
    } elseif (!$hasOpenShift) {
        $newStatus = 'offline';
    }

    if ($newStatus) {
        DB::table('drivers')->where('id', $driver->id)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
        ]);
    }

    return response()->json(['ok' => true]);
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
