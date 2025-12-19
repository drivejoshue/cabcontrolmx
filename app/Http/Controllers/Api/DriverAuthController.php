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

        // Driver vinculado al user
        $driver = DB::table('drivers')->where('user_id',$user->id)->first();
        if (!$driver) {
            return response()->json(['message'=>'Usuario no vinculado a conductor'], 403);
        }

        $token = $user->createToken('driver-app')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'driver'  => [
                'id'    => $driver->id,
                'name'  => $driver->name,
                'phone' => $driver->phone,
            ],
            'tenant'  => $user->tenant_id ?? null,
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['ok'=>true]);
    }

 public function me(Request $r)
{
    // ============================================================
    // FASE 0) Usuario autenticado y tenant consistente
    // ============================================================
    $user = $r->user();

    $userTenant = (int)($user->tenant_id ?? 0);
    if ($userTenant <= 0) {
        return response()->json(['ok'=>false,'message'=>'Driver sin tenant asignado'], 403);
    }

    // Si el cliente manda X-Tenant-ID, debe coincidir con el tenant del token.
    $headerTenant = $r->header('X-Tenant-ID');
    if ($headerTenant && (int)$headerTenant !== $userTenant) {
        return response()->json(['ok'=>false,'message'=>'Tenant inválido para este driver'], 403);
    }

    $tenantId = $userTenant;

    // ============================================================
    // FASE 1) Resolver driver
    // ============================================================
    $driver = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->select(
            'id','tenant_id','name','phone','email','foto_path','status',
            'last_lat','last_lng','last_ping_at','last_seen_at','last_bearing','last_speed',
            'last_active_status','last_active_at',
            DB::raw('payout_account_number as transfer_account'),
            DB::raw('payout_bank as transfer_bank'),
            DB::raw('payout_account_name as transfer_name'),
            DB::raw('payout_clabe as transfer_clabe'),
            DB::raw('payout_notes as transfer_notes')
        )
        ->first();

    if (!$driver) {
        return response()->json(['ok'=>false,'message'=>'Usuario no vinculado a conductor'], 403);
    }

    // ============================================================
    // FASE 2) Turno abierto y vehículo actual (si aplica)
    // ============================================================
    $shift = DB::table('driver_shifts')
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driver->id)
        ->whereNull('ended_at')
        ->orderByDesc('started_at')
        ->first();

    $vehicle = null;
    if ($shift && $shift->vehicle_id) {
        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $shift->vehicle_id)
            ->select('id','economico','plate','brand','model','type')
            ->first();
    }

    // ============================================================
    // FASE 3) Tenant (incluye banderas operativas)
    // ============================================================
    $tenant = DB::table('tenants')
        ->where('id', $tenantId)
        ->select(
            'id','name','slug','timezone','utc_offset_minutes',
            'latitud','longitud','coverage_radius_km',
            'allow_marketplace','billing_mode','commission_percent',
            'public_active'
        )
        ->first();

    // ============================================================
    // FASE 4) Billing profile del tenant (subset)
    // ============================================================
    $billing = DB::table('tenant_billing_profiles')
        ->where('tenant_id', $tenantId)
        ->select(
            'id','tenant_id','plan_code','billing_model','status',
            'trial_ends_at','trial_vehicles',
            'base_monthly_fee','included_vehicles','price_per_vehicle','max_vehicles',
            'accepted_terms_at',
            'suspended_at','suspension_reason'
        )
        ->first();

    // ============================================================
    // FASE 5) Estado calculado para que Kotlin bloquee UI/acciones
    // ============================================================
    $billingState = 'ok';
    $billingMsg   = null;

    if (!$tenant || (isset($tenant->public_active) && (int)$tenant->public_active === 0)) {
        $billingState = 'tenant_inactive';
        $billingMsg   = 'La central está desactivada.';
    } elseif (!$billing) {
        $billingState = 'no_profile';
        $billingMsg   = 'La central no tiene perfil de facturación.';
    } else {
        $st = strtolower((string)$billing->status);

        // Bloqueo duro por suspensión (impago / bloqueo manual)
        if (!empty($billing->suspended_at) || in_array($st, ['paused','canceled'], true)) {
            $billingState = 'suspended';
            $billingMsg   = $billing->suspension_reason ?: 'Servicio suspendido por facturación.';
        } elseif ($st === 'trial') {
            // Si trial venció => requiere aceptación + recarga (según tu nueva política)
            if (!empty($billing->trial_ends_at) && now()->toDateString() > $billing->trial_ends_at) {
                $billingState = 'trial_expired';
                $billingMsg   = 'Tu periodo de prueba finalizó. Recarga saldo para continuar.';
            } else {
                $billingState = 'trial';
                $billingMsg   = 'Periodo de prueba activo.';
            }
        }
    }

    return response()->json([
        'ok'             => true,
        'user'           => [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'tenant_id' => $tenantId,
        ],
        'driver'         => $driver,
        'has_open_shift' => (bool)$shift,
        'current_shift'  => $shift,
        'vehicle'        => $vehicle,
        'tenant'         => $tenant,
        'billing_profile'=> $billing,
        'billing_state'  => $billingState,
        'billing_message'=> $billingMsg,
    ]);
}




    /**
     * Cambiar disponibilidad del driver (idle/busy).
     *
     * Reglas:
     * - status permitido: idle | busy
     * - Si el driver está en on_ride, NO se sobreescribe status; solo se actualiza last_active_status.
     * - Si no está en on_ride, status y last_active_status quedan iguales.
     *
     * Payload soportado:
     * - { "available": true/false }  (true->idle, false->busy)
     * - { "status": "idle"|"busy" }
     */
   public function setStatus(Request $r)
    {
    $user = $r->user();

    $userTenant = (int)($user->tenant_id ?? 0);
    if ($userTenant <= 0) {
        return response()->json(['ok'=>false,'message'=>'Driver sin tenant asignado'], 403);
    }

    $headerTenant = $r->header('X-Tenant-ID');
    if ($headerTenant && (int)$headerTenant !== $userTenant) {
        return response()->json(['ok'=>false,'message'=>'Tenant inválido para este driver'], 403);
    }

    $tenantId = $userTenant;

    $data = $r->validate([
        'available' => 'nullable|boolean',
        // si lo mantienes, que siga siendo SOLO idle|busy
        'status'    => 'nullable|string|in:idle,busy',
    ]);

    // Resolver status final (solo idle/busy)
    $newStatus = null;
    if (array_key_exists('available', $data)) {
        $newStatus = $data['available'] ? 'idle' : 'busy';
    } elseif (!empty($data['status'])) {
        $newStatus = $data['status'];
    }

    if (!$newStatus) {
        return response()->json([
            'ok' => false,
            'message' => 'Debe enviar available (bool) o status (idle|busy)'
        ], 422);
    }

    $driver = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $user->id)
        ->select('id','status','last_active_status')
        ->first();

    if (!$driver) {
        return response()->json(['ok'=>false,'message'=>'Usuario no vinculado a conductor'], 403);
    }

    $now = now();

    // Guardamos preferencia (idle/busy) SIEMPRE
    $update = [
        'last_active_status' => $newStatus,
        'last_active_at'     => $now,
        'updated_at'         => $now,
    ];

    // Solo pisamos status si NO está on_ride (normalizado)
    $current = strtolower(trim((string)($driver->status ?? '')));
    if ($current !== 'on_ride') {
        $update['status'] = $newStatus; // aquí sí actualizas status real (idle/busy)
    }

    DB::table('drivers')->where('id', $driver->id)->update($update);

    return $this->me($r);
}


   
  /**
 * Actualizar cuenta para transferencias del driver
 */
    public function updateBank(Request $r)
    {
        $user     = $r->user();
        $tenantId = $r->header('X-Tenant-ID') ?: ($user->tenant_id ?? null);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if (!$driver) {
            return response()->json([
                'ok'      => false,
                'message' => 'Usuario no vinculado a conductor',
            ], 403);
        }

        $data = $r->validate([
            'transfer_account' => 'nullable|string|max:60',   // número de cuenta / tarjeta
            'transfer_bank'    => 'nullable|string|max:80',   // nombre del banco
            'transfer_name'    => 'nullable|string|max:120',  // nombre del titular
            'transfer_clabe'   => 'nullable|string|max:20',   // CLABE (opcional)
            'transfer_notes'   => 'nullable|string|max:255',  // notas internas
        ]);

        DB::table('drivers')
            ->where('id', $driver->id)
            ->update([
                'payout_account_number' => $data['transfer_account'] ?? null,
                'payout_bank'           => $data['transfer_bank'] ?? null,
                'payout_account_name'   => $data['transfer_name'] ?? null,
                'payout_clabe'          => $data['transfer_clabe'] ?? null,
                'payout_notes'          => $data['transfer_notes'] ?? null,
                'updated_at'            => now(),
            ]);

        // Reutilizamos me() para devolver todo fresco
        return $this->me($r);
    }

}
