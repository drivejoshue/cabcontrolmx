<?php  // app/Http/Controllers/Api/PassengerAuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PassengerAuthController extends Controller
{
    public function syncFromFirebase(Request $request)
    {
        // 🚨 TODO futuro: verificar idToken de Firebase aquí
        // (por ahora confiamos en el cliente para avanzar rápido).

        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
            'name'         => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:160',
            'avatar_url'   => 'nullable|string|max:255',
            'platform'     => 'nullable|string|max:20',
            'app_version'  => 'nullable|string|max:20',
        ]);

        // Por ahora usamos tenant_id fijo para pasajeros globales
        $tenantId = 1; // luego lo puedes hacer configurable

        $firebaseUid = $data['firebase_uid'];
        $phone       = $data['phone'] ?? null;
        $email       = $data['email'] ?? null;

        // 1) Buscar por firebase_uid
        $passenger = Passenger::where('firebase_uid', $firebaseUid)->first();

        // 2) Si no existe, intentar por telefono (por si lo tenías de antes)
        if (! $passenger && $phone) {
            $passenger = Passenger::where('tenant_id', $tenantId)
                ->where('phone', $phone)
                ->first();
        }

        // 3) Si no existe, intentar por email dentro del mismo tenant
        if (! $passenger && $email) {
            $passenger = Passenger::where('tenant_id', $tenantId)
                ->where('email', $email)
                ->first();
        }

        $payload = [
            'tenant_id'  => $tenantId,
            'firebase_uid' => $firebaseUid,
            'name'       => $data['name'] ?? null,
            'phone'      => $phone,
            'email'      => $data['email'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ];

        if (! $passenger) {
            $passenger = Passenger::create($payload);
        } else {
            $passenger->fill(Arr::where($payload, fn ($v) => !is_null($v)));
            $passenger->save();
        }

        return response()->json([
            'data' => [
                'id'          => $passenger->id,
                'tenant_id'   => $passenger->tenant_id,
                'firebase_uid'=> $passenger->firebase_uid,
                'name'        => $passenger->name,
                'phone'       => $passenger->phone,
                'email'       => $passenger->email,
                'avatar_url'  => $passenger->avatar_url,
            ],
        ]);
    }

    public function ping(Request $request)
{
    $data = $request->validate([
        'firebase_uid' => 'required|string|max:128',
        'platform'     => 'nullable|string|max:20',
        'app_version'  => 'nullable|string|max:20',
    ]);

    $firebaseUid = $data['firebase_uid'];

    // Buscar pasajero solo por firebase_uid (sin tenant en el payload)
    $passenger = Passenger::where('firebase_uid', $firebaseUid)->first();

    if (! $passenger) {
        return response()->json([
            'ok'  => false,
            'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
        ], 422);
    }

    // (Opcional) si luego agregas last_seen_at:
    // $passenger->forceFill(['last_seen_at' => now()])->save();

    return response()->json([
        'ok'  => true,
        'msg' => 'pong',
        // opcional si quieres regresar algo útil:
        // 'data' => [
        //     'id'        => $passenger->id,
        //     'tenant_id' => $passenger->tenant_id,
        // ]
    ]);
}

}
