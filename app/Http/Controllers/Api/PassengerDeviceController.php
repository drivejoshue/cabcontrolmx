<?php 
// app/Http/Controllers/Api/PassengerDeviceController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\PassengerDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PassengerDeviceController extends Controller
{
    /**
     * Registra o actualiza un device de pasajero.
     *
     * Se recomienda llamarlo:
     *  - Después de /passenger/auth-sync
     *  - Cada vez que Firebase genere un nuevo FCM token
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
            'device_id'    => 'required|string|max:191',
            'fcm_token'    => 'required|string|max:512',
            'platform'     => 'nullable|string|max:20',   // 'android', 'ios'
            'app_version'  => 'nullable|string|max:20',
            'os_version'   => 'nullable|string|max:20',
        ]);

        // 1) Localizar al pasajero por firebase_uid (global)
        $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();

        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, primero llama a /passenger/auth-sync.',
            ], 422);
        }

        // 2) Upsert por (passenger_id, device_id)
        $device = PassengerDevice::where('passenger_id', $passenger->id)
            ->where('device_id', $data['device_id'])
            ->first();

        $payload = [
            'passenger_id' => $passenger->id,
            'firebase_uid' => $data['firebase_uid'],
            'device_id'    => $data['device_id'],
            'fcm_token'    => $data['fcm_token'],
            'platform'     => $data['platform'] ?? 'android',
            'app_version'  => $data['app_version'] ?? null,
            'os_version'   => $data['os_version'] ?? null,
            'is_active'    => true,
            'last_seen_at' => Carbon::now(),
        ];

        if (! $device) {
            $device = PassengerDevice::create($payload);
        } else {
            $device->fill($payload);
            $device->save();
        }

        return response()->json([
            'ok'   => true,
            'msg'  => 'device sync ok',
            'data' => [
                'id'          => $device->id,
                'passenger_id'=> $device->passenger_id,
                'platform'    => $device->platform,
                'app_version' => $device->app_version,
                'is_active'   => $device->is_active,
            ],
        ]);
    }

    /**
     * Marcar un token como inactivo (ej. logout o token inválido).
     */
    public function deleteToken(Request $request)
    {
        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
            'device_id'    => 'required|string|max:191',
            'fcm_token'    => 'nullable|string|max:512',
        ]);

        $query = PassengerDevice::where('firebase_uid', $data['firebase_uid'])
            ->where('device_id', $data['device_id']);

        if (!empty($data['fcm_token'])) {
            $query->where('fcm_token', $data['fcm_token']);
        }

        $device = $query->first();

        if (! $device) {
            return response()->json([
                'ok'  => true,
                'msg' => 'device ya no existe (nada que hacer)',
            ]);
        }

        $device->is_active   = false;
        $device->fcm_token   = null;           // opcional: lo vacías
        $device->last_seen_at= Carbon::now();
        $device->save();

        return response()->json([
            'ok'  => true,
            'msg' => 'device desactivado',
        ]);
    }
}
