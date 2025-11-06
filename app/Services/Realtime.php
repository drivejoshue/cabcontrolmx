<?php

namespace App\Services;

use App\Events\DriverEvent;

class Realtime
{
    public static function toDriver(int $tenantId, int $driverId): self
    {
        return new self($tenantId, $driverId);
    }

    public function __construct(
        private int $tenantId,
        private int $driverId
    ) {}

    public function emit(string $event, array $data): void
    {
        event(new DriverEvent(
            tenantId: $this->tenantId,
            driverId: $this->driverId,
            type: $event,
            payload: $data
        ));
    }
}