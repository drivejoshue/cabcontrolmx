<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PassengerRideShareController extends Controller
{
    /**
     * POST /api/passenger/rides/{ride}/share
     * Body: tenant_id, firebase_uid
     */
    public function create(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string|max:128',
        ]);

        $tenantId = (int)$v['tenant_id'];

        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (!$passenger) {
            return response()->json(['ok' => false, 'msg' => 'Pasajero no encontrado'], 422);
        }

        $rideRow = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride)
            ->first(['id','tenant_id','passenger_id','status']);

        if (!$rideRow) {
            return response()->json(['ok' => false, 'msg' => 'Ride no encontrado'], 404);
        }

        if ((int)$rideRow->passenger_id !== (int)$passenger->id) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado'], 403);
        }

        $status = strtolower((string)$rideRow->status);
        if (in_array($status, ['finished','canceled'], true)) {
            return response()->json(['ok' => false, 'msg' => 'El viaje ya está cerrado'], 409);
        }

        // Idempotencia: si ya hay uno activo vigente, reusar
        $now = now();

        $existing = DB::table('ride_shares')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->where('passenger_id', $passenger->id)
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $url = url("/ride_share/{$existing->token}");
            return response()->json([
                'ok'    => true,
                'token' => $existing->token,
                'url'   => $url,
            ]);
        }

        $token = $this->newToken();

        // Guardrail TTL (aunque el objetivo real es “hasta que termine”)
        $expiresAt = $now->copy()->addHours(8);

        DB::table('ride_shares')->insert([
            'tenant_id'     => $tenantId,
            'ride_id'       => $ride,
            'passenger_id'  => $passenger->id,
            'token'         => $token,
            'status'        => 'active',
            'expires_at'    => $expiresAt,
            'views_count'   => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $url = url("/ride_share/{$token}");

        return response()->json([
            'ok'        => true,
            'token'     => $token,
            'url'       => $url,
            'expires_at'=> $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /api/passenger/rides/{ride}/share/revoke
     * Body: tenant_id, firebase_uid
     */
    public function revoke(Request $req, int $ride)
    {
        $v = $req->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string|max:128',
        ]);

        $tenantId = (int)$v['tenant_id'];

        $passenger = Passenger::where('firebase_uid', $v['firebase_uid'])->first();
        if (!$passenger) {
            return response()->json(['ok' => false, 'msg' => 'Pasajero no encontrado'], 422);
        }

        $now = now();

        DB::table('ride_shares')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->where('passenger_id', $passenger->id)
            ->where('status', 'active')
            ->update([
                'status'     => 'revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json(['ok' => true]);
    }

    private function newToken(): string
    {
        // 64 chars aprox. (no adivinable)
        return Str::random(64);
    }
}
