<?php
namespace App\Services;
use App\Events\DriverEvent;

class Realtime
{
    private function __construct(private int $tenantId, private int $driverId) {}

    public static function toDriver(int $tenantId, int $driverId): self {
        return new self($tenantId, $driverId);
    }
    public function emit(string $type, array $payload): void {
        event(new DriverEvent($this->tenantId, $this->driverId, $type, $payload));
    }
}
