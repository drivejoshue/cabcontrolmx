<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RideBroadcaster
{
    /** Payload base consistente */
    private static function basePayload(int $rideId, string $status, array $extra = []): array
    {
        return array_merge([
            'ride_id' => $rideId,
            'status'  => $status,
            'at'      => now()->format('Y-m-d H:i:s'),
        ], $extra);
    }

    /** Lee driver_id del ride, si existe */
    private static function rideDriverId(int $rideId): ?int
    {
        $d = DB::table('rides')->where('id', $rideId)->value('driver_id');
        return $d ? (int)$d : null;
    }

    /** Emitir a canal del ride y (si aplica) al driver */
    public static function update(int $tenantId, int $rideId, string $status, array $extra = []): void
    {
        $payload = self::basePayload($rideId, $status, $extra);

        // Canal del ride (panel / pasajero)
        Realtime::toRide($tenantId, $rideId)->emit('ride.update', $payload);

        // Canal del driver (si asignado)
        if ($driverId = self::rideDriverId($rideId)) {
            Realtime::toDriver($tenantId, $driverId)->emit('ride.update', $payload);
        }
    }

    /* -----------------------------------------------------------------
     * NUEVO 1: cuando se crea desde Passenger → “buscando conductor”
     * -----------------------------------------------------------------*/
    public static function requestedFromPassenger(
        int $tenantId,
        int $rideId,
        ?int $recommended,
        ?int $passengerOffer,
        ?int $distanceM = null,
        ?int $durationS = null
    ): void {
        $extra = [
            'phase'            => 'searching',
            'recommended_fare' => $recommended,
            'passenger_offer'  => $passengerOffer,
            'distance_m'       => $distanceM,
            'duration_s'       => $durationS,
            'requested_channel'=> 'passenger_app',
        ];

        // Quitamos nulos para no ensuciar el payload
        $extra = array_filter($extra, fn($v) => $v !== null);

        self::update($tenantId, $rideId, 'requested', $extra);
    }

    /* -----------------------------------------------------------------
     * NUEVO 2: propuesta del driver (bidding) → Passenger ve oferta
     * -----------------------------------------------------------------*/
    public static function bidProposed(
        int $tenantId,
        int $rideId,
        int $offerId,
        int $driverId,
        int $driverAmount,
        ?int $passengerOffer = null
    ): void {
        $extra = [
            'phase'           => 'bidding_proposed',
            'offer_id'        => $offerId,
            'driver_id'       => $driverId,
            'driver_offer'    => $driverAmount,
            'passenger_offer' => $passengerOffer,
        ];

        self::update($tenantId, $rideId, 'requested', $extra);
    }

    /* -----------------------------------------------------------------
     * NUEVO 3: resultado del bidding visto desde Passenger (aceptado / rechazado)
     * -----------------------------------------------------------------*/
    public static function bidResult(
        int $tenantId,
        int $rideId,
        int $offerId,
        string $result,       // 'accepted' o 'rejected'
        ?int $agreedAmount = null
    ): void {
        $extra = [
            'phase'       => 'bidding_result',
            'offer_id'    => $offerId,
            'bid_result'  => $result,
        ];
        if ($agreedAmount !== null) {
            $extra['agreed_amount'] = $agreedAmount;
        }

        // El status del ride ya será 'accepted' o seguirá 'requested' según el caso
        self::update($tenantId, $rideId, $result === 'accepted' ? 'accepted' : 'requested', $extra);
    }

    /* -----------------------------------------------------------------
     * NUEVO 4: ubicación en tiempo real para el cochecito
     * -----------------------------------------------------------------*/
    public static function location(
        int $tenantId,
        int $rideId,
        float $lat,
        float $lng,
        ?float $bearing = null
    ): void {
        $payload = [
            'ride_id' => $rideId,
            'lat'     => $lat,
            'lng'     => $lng,
            'at'      => now()->format('Y-m-d H:i:s'),
        ];
        if ($bearing !== null) $payload['bearing'] = $bearing;

        Realtime::toRide($tenantId, $rideId)->emit('ride.location', $payload);
    }

    /* ==================== LO QUE YA TENÍAS ==================== */

    /** Después de aceptar (winner) */
    public static function afterAccept(
        int $tenantId,
        int $rideId,
        int $driverId,
        int $offerId,
        ?float $agreedAmount = null       // ⬅️ nuevo parámetro opcional
    ): void {
        // Señal directa al driver
        Realtime::toDriver($tenantId, $driverId)->emit('ride.active', [
            'ride_id'      => $rideId,
            'offer_id'     => $offerId,
            'agreed_amount'=> $agreedAmount,
        ]);

        // Señal al canal del ride
        $extra = ['driver_id' => $driverId, 'offer_id' => $offerId];
        if ($agreedAmount !== null) {
            $extra['agreed_amount'] = $agreedAmount;
        }

        self::update($tenantId, $rideId, 'accepted', $extra);
    }

    public static function arrived(int $tenantId, int $rideId): void
    {
        self::update($tenantId, $rideId, 'arrived', [
            'arrived_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public static function onboard(int $tenantId, int $rideId): void
    {
        self::update($tenantId, $rideId, 'on_board', [
            'onboard_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public static function finished(int $tenantId, int $rideId, ?float $total = null): void
    {
        $extra = ['finished_at' => now()->format('Y-m-d H:i:s')];
        if ($total !== null) $extra['total_amount'] = $total;
        self::update($tenantId, $rideId, 'finished', $extra);
    }

    public static function canceled(int $tenantId, int $rideId, ?string $by = null, ?string $reason = null): void
    {
        self::update($tenantId, $rideId, 'canceled', [
            'canceled_by'   => $by,
            'cancel_reason' => $reason,
            'canceled_at'   => now()->format('Y-m-d H:i:s'),
        ]);
    }


    public static function passengerOnWay(int $tenantId, int $rideId): void
    {
        // No cambia el status real, sólo manda un flag extra
        self::update($tenantId, $rideId, 'arrived', [
            'passenger_on_way' => true,
        ]);
    }


    public static function stopDone(int $tenantId, int $rideId, int $seq): void
    {
        self::update($tenantId, $rideId, 'stop_done', ['seq' => $seq]);
    }
}
