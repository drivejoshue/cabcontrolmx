<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
   private static function rideDriverId(int $tenantId, int $rideId): ?int
{
    $d = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $rideId)
        ->value('driver_id');

    return $d ? (int)$d : null;
}


    /**
     * Emitir a canal del RIDE y (si aplica) al DRIVER
     * Sólo para cambios de estado canónico (requested/accepted/arrived/on_board/finished/…)
     */
    public static function update(int $tenantId, int $rideId, string $status, array $extra = []): void
    {
        $payload = self::basePayload($rideId, $status, $extra);

        // Canal del ride (panel + passenger app)
        Realtime::toRide($tenantId, $rideId)->emit('ride.update', $payload);

        // Canal del driver (si ya hay ganador)
        if ($driverId = self::rideDriverId($tenantId, $rideId)) {
    Realtime::toDriver($tenantId, $driverId)->emit('ride.update', $payload);
        }

        \Log::info('RideBroadcaster.update OUT', [
  'tenant_id' => $tenantId,
  'ride_id'   => $rideId,
  'status'    => $status,
  'channel'   => "tenant.$tenantId.ride.$rideId",
]);
    }

    /* -----------------------------------------------------------------
     * 1) Cuando se crea desde Passenger → “buscando conductor”
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
            'phase'             => 'searching',
            'recommended_fare'  => $recommended,
            'passenger_offer'   => $passengerOffer,
            'distance_m'        => $distanceM,
            'duration_s'        => $durationS,
            'requested_channel' => 'passenger_app',
        ];

        // Quitamos nulos
        $extra = array_filter($extra, fn ($v) => $v !== null);

        self::update($tenantId, $rideId, 'requested', $extra);
    }

    /* -----------------------------------------------------------------
     * 2) Opcional: resumen de ofertas activas → “1 conductor viendo…”
     * -----------------------------------------------------------------*/
    public static function offersSummary(int $tenantId, int $rideId): void
    {
        $active = DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $rideId)
            ->whereIn('status', ['offered', 'pending_passenger'])
            ->count();

        Realtime::toRide($tenantId, $rideId)->emit('ride.offers_summary', [
            'ride_id'       => $rideId,
            'active_offers' => $active,
        ]);
    }

    /* -----------------------------------------------------------------
     * 3) Propuesta del driver (BID) → Passenger ve oferta
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

        

        // De paso actualizamos el contador de “drivers viendo tu viaje”
        self::offersSummary($tenantId, $rideId);
    }

    /* -----------------------------------------------------------------
     * 4) Resultado del bidding (aceptado / rechazado por passenger)
     * -----------------------------------------------------------------*/
    public static function bidResult(
        int $tenantId,
        int $rideId,
        int $offerId,
        string $result,       // 'accepted' o 'rejected'
        ?int $agreedAmount = null
    ): void {
        $extra = [
            'phase'      => 'bidding_result',
            'offer_id'   => $offerId,
            'bid_result' => $result,
        ];
        if ($agreedAmount !== null) {
            $extra['agreed_amount'] = $agreedAmount;
        }

        // Si aceptaron → status accepted, si no → se queda requested
        $status = $result === 'accepted' ? 'accepted' : 'requested';
        self::update($tenantId, $rideId, $status, $extra);
    }

    /* -----------------------------------------------------------------
     * 5) Ubicación en tiempo real del cochecito
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
        'lat'     => (float) $lat,
        'lng'     => (float) $lng,
        'at'      => now()->format('Y-m-d H:i:s'),
    ];

    if ($bearing !== null) {
        $payload['bearing'] = (float) $bearing;
    }

    \Log::info('RideBroadcaster.location OUT', [
        'tenant_id' => $tenantId,
        'ride_id'   => $rideId,
        'lat'       => $payload['lat'],
        'lng'       => $payload['lng'],
        'bearing'   => $bearing,
    ]);

    // Canal tenant.{tenantId}.ride.{rideId}
    Realtime::toRide($tenantId, $rideId)
        ->emit('ride.location', $payload);
}

public static function driverToPickupRoute(
    int $tenantId,
    int $rideId,
    string $polyline,
    int $distanceM,
    int $durationS
): void {
    $payload = [
        'ride_id'     => $rideId,
        'type'        => 'driver_to_pickup',
        'polyline'    => $polyline,
        'distance_m'  => $distanceM,
        'duration_s'  => $durationS,
    ];

    \Log::info('RideBroadcaster.driverToPickupRoute OUT', [
        'tenant_id'  => $tenantId,
        'ride_id'    => $rideId,
        'distance_m' => $distanceM,
        'duration_s' => $durationS,
    ]);

     Realtime::toRide($tenantId, $rideId)
        ->emit('ride.driver_to_pickup_route', $payload);
}



    /* ==================== ESTADOS CANÓNICOS (DRIVER) ==================== */
public static function bootstrapLocationAndRoute(
    int $tenantId,
    int $rideId,
    ?int $forcedDriverId = null
): void {
    // 1) Leer ride
    $ride = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $rideId)
        ->first();

    if (! $ride) {
        \Log::warning('bootstrapLocationAndRoute: ride no encontrado', [
            'tenant_id' => $tenantId,
            'ride_id'   => $rideId,
        ]);
        return;
    }

    // Sólo nos interesa para viajes de passenger_app
    if (($ride->requested_channel ?? null) !== 'passenger_app') {
        \Log::info('bootstrapLocationAndRoute: ride no es de passenger_app, se omite', [
            'tenant_id'         => $tenantId,
            'ride_id'           => $rideId,
            'requested_channel' => $ride->requested_channel,
        ]);
        return;
    }

    // 2) Obtener driver asignado
   $driverId = $forcedDriverId ?: self::rideDriverId($tenantId, $rideId);

    if (! $driverId) {
        \Log::warning('bootstrapLocationAndRoute: ride sin driver asignado', [
            'tenant_id' => $tenantId,
            'ride_id'   => $rideId,
        ]);
        return;
    }

    // 3) Última ubicación del driver
    $loc = DB::table('driver_locations')
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driverId)
        ->orderByDesc('id')
        ->first();

    if ($loc && $loc->lat && $loc->lng) {
        \Log::info('bootstrapLocationAndRoute: enviando primera ride.location', [
            'tenant_id' => $tenantId,
            'ride_id'   => $rideId,
            'driver_id' => $driverId,
            'lat'       => (float) $loc->lat,
            'lng'       => (float) $loc->lng,
        ]);

        self::location(
            $tenantId,
            $rideId,
            (float) $loc->lat,
            (float) $loc->lng,
            $loc->bearing ? (float) $loc->bearing : null
        );
    } else {
        \Log::warning('bootstrapLocationAndRoute: driver sin driver_locations aún', [
            'tenant_id' => $tenantId,
            'ride_id'   => $rideId,
            'driver_id' => $driverId,
        ]);
    }

    // ✅ Sin cálculo de ruta.
    // La app Passenger usará este stream de ride.location
    // para construir la ruta en Kotlin directamente.
}


    /** Después de aceptar (winner) */
   public static function afterAccept(
    int $tenantId,
    int $rideId,
    int $driverId,
    int $offerId,
    ?float $agreedAmount = null
): void {
    // Señal directa al driver (OrbanaDriver → RideScreen)
    Realtime::toDriver($tenantId, $driverId)->emit('ride.active', [
        'ride_id'       => $rideId,
        'offer_id'      => $offerId,
        'agreed_amount' => $agreedAmount,
    ]);

    // Señal al canal del ride (Passenger + panel)
    $extra = [
        'driver_id' => $driverId,
        'offer_id'  => $offerId,
    ];
    if ($agreedAmount !== null) {
        $extra['agreed_amount'] = $agreedAmount;
    }

    self::update($tenantId, $rideId, 'accepted', $extra);

    // 🔥 Aquí forzamos la primera ubicación + ruta para el passenger_app
    self::bootstrapLocationAndRoute($tenantId, $rideId, $driverId);
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
        if ($total !== null) {
            $extra['total_amount'] = $total;
        }
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

    /* ==================== FLAGS DEL PASAJERO (NO CAMBIAN STATUS) ==================== */

   public static function passengerOnWay(int $tenantId, int $rideId): void
    {
        // No cambia el status real, sólo manda un flag extra
        self::update($tenantId, $rideId, 'arrived', [
            'passenger_on_way' => true,
        ]);
    }

    public static function passengerOnBoard(int $tenantId, int $rideId): void
    {
        // El pasajero dice "ya estoy a bordo", pero el on_board real lo hace el driver
        $payload = [
            'ride_id'            => $rideId,
            'status'             => null, // no tocamos status canónico
            'passenger_on_board' => true,
            'at'                 => now()->toDateTimeString(),
        ];

        // Canal ride (passenger + panel)
        Realtime::toRide($tenantId, $rideId)->emit('ride.update', $payload);

        // También se lo avisamos al driver
        if ($driverId = self::rideDriverId($rideId)) {
            Realtime::toDriver($tenantId, $driverId)->emit('ride.update', $payload);
        }
    }

    public static function passengerFinished(int $tenantId, int $rideId): void
    {
        // El pasajero indica que ya llegó / terminó, pero el finish real lo hace el driver
        $payload = [
            'ride_id'            => $rideId,
            'status'             => null, // driver sigue siendo quien hace el finish real
            'passenger_finished' => true,
            'at'                 => now()->toDateTimeString(),
        ];

        Realtime::toRide($tenantId, $rideId)->emit('ride.update', $payload);

        if ($driverId = self::rideDriverId($rideId)) {
            Realtime::toDriver($tenantId, $driverId)->emit('ride.update', $payload);
        }
    }


    public static function stopDone(int $tenantId, int $rideId, int $seq): void
    {
        self::update($tenantId, $rideId, 'stop_done', ['seq' => $seq]);
    }

 public static function offerViewing(
        int   $tenantId,
        int   $rideId,
        int   $offerId,
        int   $driverId,
        string $status,          // "start" | "stop"
        array $driver = []       // datos que ya armaste en OfferController@viewing
    ) {
        $payload = [
            'ride_id'   => $rideId,
            'offer_id'  => $offerId,
            'driver_id' => $driverId,
            'status'    => $status,   // "start" / "stop"
            'driver'    => $driver,   // name, avatar_url, vehicle_*, eta_seconds, distance_m...
        ];

        try {
            Log::info('RideBroadcaster.offerViewing sending', [
                'tenant_id' => $tenantId,
                'payload'   => $payload,
            ]);

            // Canal: tenant.{tenant}.ride.{ride}
            Realtime::toRide($tenantId, $rideId)
                ->emit('ride.offer_viewing', $payload);

            Log::info('RideBroadcaster.offerViewing sent', [
                'tenant_id' => $tenantId,
                'ride_id'   => $rideId,
                'offer_id'  => $offerId,
            ]);

        } catch (\Throwable $e) {
            Log::error('RideBroadcaster.offerViewing error', [
                'tenant_id' => $tenantId,
                'ride_id'   => $rideId,
                'offer_id'  => $offerId,
                'error'     => $e->getMessage(),
            ]);
            // Importante: NO relanzar, para que el controller pueda seguir y devolver ok=true
        }
    }
}
