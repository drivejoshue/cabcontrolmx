<?php

namespace App\Services;

use App\Events\DriverEvent;
use App\Events\RideEvent;

class Realtime
{
    /** Encadenador para DRIVER */
    public static function toDriver(int $tenantId, int $driverId): RealtimeEmitter
    {
        return new RealtimeEmitter('driver', $tenantId, $driverId, null);
    }

    /** Encadenador para RIDE */
    public static function toRide(int $tenantId, int $rideId): RealtimeEmitter
    {
        return new RealtimeEmitter('ride', $tenantId, null, $rideId);
    }
}

class RealtimeEmitter
{
    public function __construct(
        private string $type,
        private int $tenantId,
        private ?int $driverId,
        private ?int $rideId
    ) {}

    public function emit(string $event, array $payload = []): void
    {
        if ($this->type === 'driver' && $this->driverId) {
            broadcast(new DriverEvent($this->tenantId, $this->driverId, $event, $payload));
        } elseif ($this->type === 'ride' && $this->rideId) {
            broadcast(new RideEvent($this->tenantId, $this->rideId, $event, $payload));
        }
    }
}
