<?php  // app/Http/Controllers/Api/PassengerAuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PassengerAuthController extends Controller
{
    public function syncFromFirebase(Request $request)
    {
        // 🚨 TODO futuro: verificar idToken de Firebase aquí

        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
            'name'         => 'nullable|string|max:120',
            'phone'        => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:160',
            'avatar_url'   => 'nullable|string|max:255',
            'platform'     => 'nullable|string|max:20',
            'app_version'  => 'nullable|string|max:20',
        ]);

        // Si quieres seguir usando un tenant "global", hazlo configurable:
        // $tenantId = (int) config('orbana.global_passenger_tenant_id', 100);
        // Si los pasajeros son verdaderamente globales, puedes dejar tenant_id en null.
        $tenantId = 100;

        $firebaseUid = $data['firebase_uid'];
        $phone       = $data['phone'] ?? null;
        $email       = $data['email'] ?? null;

        // 1) Buscar por firebase_uid (global)
        $passenger = Passenger::where('firebase_uid', $firebaseUid)->first();

        // 2) Si no existe, intentar por telefono (global)
        if (! $passenger && $phone) {
            $passenger = Passenger::where('phone', $phone)->first();
        }

        // 3) Si no existe, intentar por email (global)
        if (! $passenger && $email) {
            $passenger = Passenger::where('email', $email)->first();
        }

        $payload = [
            'tenant_id'    => $tenantId,            // null o global configurable
            'firebase_uid' => $firebaseUid,
            'name'         => $data['name'] ?? null,
            'phone'        => $phone,
            'email'        => $data['email'] ?? null,
            'avatar_url'   => $data['avatar_url'] ?? null,
        ];

        if (! $passenger) {
            $passenger = Passenger::create($payload);
        } else {
            $passenger->fill(Arr::where($payload, fn ($v) => !is_null($v)));
            $passenger->save();
        }

        return response()->json([
            'data' => [
                'id'           => $passenger->id,
                'tenant_id'    => $passenger->tenant_id,
                'firebase_uid' => $passenger->firebase_uid,
                'name'         => $passenger->name,
                'phone'        => $passenger->phone,
                'email'        => $passenger->email,
                'avatar_url'   => $passenger->avatar_url,
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

        // Buscar pasajero solo por firebase_uid (global, sin tenant)
        $passenger = Passenger::where('firebase_uid', $firebaseUid)->first();

        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, sincroniza primero /passenger/auth-sync.',
            ], 422);
        }

        return response()->json([
            'ok'  => true,
            'msg' => 'pong',
        ]);
    }


    // app/Http/Controllers/Api/PassengerAuthController.php

public function profile(Request $request)
{
    $data = $request->validate([
        'firebase_uid' => 'required|string|max:128',
    ]);

    $passenger = \App\Models\Passenger::where('firebase_uid', $data['firebase_uid'])->first();

    if (! $passenger) {
        return response()->json([
            'ok'  => false,
            'msg' => 'Pasajero no encontrado, llama primero a /passenger/auth-sync.',
        ], 404);
    }

    return response()->json([
        'ok'   => true,
        'data' => [
            'id'           => $passenger->id,
            'tenant_id'    => $passenger->tenant_id,
            'firebase_uid' => $passenger->firebase_uid,
            'name'         => $passenger->name,
            'phone'        => $passenger->phone,
            'email'        => $passenger->email,
            'avatar_url'   => $passenger->avatar_url,
        ],
    ]);
}




}
