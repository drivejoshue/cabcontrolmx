<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Services\PassengerPushService;
use Illuminate\Http\Request;

class PassengerTestPushController extends Controller
{
    public function sendTest(Request $request, PassengerPushService $push)
    {
        $data = $request->validate([
            'passenger_id' => 'nullable|integer|exists:passengers,id',
            'firebase_uid' => 'nullable|string',
            'title'        => 'nullable|string',
            'body'         => 'nullable|string',
        ]);

        if (empty($data['passenger_id']) && empty($data['firebase_uid'])) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Debes enviar passenger_id o firebase_uid',
            ], 422);
        }

        $q = Passenger::query();

        if (!empty($data['passenger_id'])) {
            $q->where('id', $data['passenger_id']);
        } else {
            $q->where('firebase_uid', $data['firebase_uid']);
        }

        $passenger = $q->first();

        if (!$passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado',
            ], 404);
        }

        $title = $data['title'] ?? 'Prueba Orbana Passenger';
        $body  = $data['body']  ?? 'Esta es una notificación de prueba desde el backend.';

        $push->notifyPassenger($passenger, 'manual_test', [
            'title' => $title,
            'body'  => $body,
        ]);

        return response()->json([
            'ok'  => true,
            'msg' => 'Notificación enviada (si hay tokens activos para este pasajero)',
        ]);
    }
}
