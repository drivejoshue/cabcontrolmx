<?php
// app/Services/PassengerPushService.php

namespace App\Services;

use App\Models\Passenger;
use App\Models\PassengerDevice;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PassengerPushService
{
    /**
     * Envía una notificación FCM HTTP v1 a TODOS los devices activos de un pasajero.
     *
     * $type: 'driver_arrived', 'ride_started', 'ride_finished', etc.
     * $data: payload extra (incluiremos, por ejemplo, ride_id, tenant_id, flags, etc.).
     */
    public function notifyPassenger(Passenger $passenger, string $type, array $data = []): void
    {
        $tokens = PassengerDevice::where('passenger_id', $passenger->id)
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();

        if (empty($tokens)) {
            Log::info("PassengerPushService: sin tokens activos para passenger_id={$passenger->id}");
            return;
        }

        // Resuelve título/cuerpo por defecto
        $title = $data['title'] ?? $this->defaultTitleForType($type);
        $body  = $data['body']  ?? $this->defaultBodyForType($type);

        // Access token OAuth2 para FCM HTTP v1
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('PassengerPushService: no se pudo obtener accessToken para FCM v1');
            return;
        }

        $projectId = config('services.fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Enviamos 1 request por token (para empezar está bien).
        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => array_merge($data, [
                        'type'         => $type,
                        'passenger_id' => (string) $passenger->id,
                    ]),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound'      => 'default',
                            'channel_id' => 'ride_updates', // mismo CHANNEL_ID de la app
                        ],
                    ],
                ],
            ];

            try {
                $resp = Http::withToken($accessToken)
                    ->post($url, $message);

                Log::info('PassengerPushService FCM v1 resp', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('PassengerPushService error enviando FCM v1', [
                    'error'        => $e->getMessage(),
                    'passenger_id' => $passenger->id,
                ]);
            }
        }
    }

    /**
     * Genera un access token OAuth2 usando el service account configurado.
     */
    protected function getAccessToken(): ?string
    {
        try {
            $projectId   = config('services.fcm.project_id');
            $clientEmail = config('services.fcm.client_email');
            $privateKey  = config('services.fcm.private_key');

            if (!$projectId || !$clientEmail || !$privateKey) {
                Log::error('PassengerPushService: faltan credenciales FCM en config/services.php');
                return null;
            }

            $config = [
                'type'         => 'service_account',
                'project_id'   => $projectId,
                'client_email' => $clientEmail,
                'private_key'  => $privateKey,
            ];

            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

            $credentials = new ServiceAccountCredentials($scopes, $config);
            $tokenData   = $credentials->fetchAuthToken();

            return $tokenData['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('PassengerPushService: error obteniendo accessToken FCM v1', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function defaultTitleForType(string $type): string
    {
        return match ($type) {
            'driver_arrived' => 'Tu conductor ha llegado',
            'ride_started'   => 'Tu viaje ha comenzado',
            'ride_finished'  => 'Viaje finalizado',
            default          => 'Orbana',
        };
    }

    private function defaultBodyForType(string $type): string
    {
        return match ($type) {
            'driver_arrived' => 'Tu conductor te espera en el punto de encuentro.',
            'ride_started'   => 'Estás en camino a tu destino.',
            'ride_finished'  => 'Gracias por viajar con Orbana.',
            default          => '',
        };
    }
}
