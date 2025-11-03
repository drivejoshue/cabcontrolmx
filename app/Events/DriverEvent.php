<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class DriverEvent implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $tenantId;
    public int $driverId;
    public string $type;
    public array $payload;

    public function __construct(int $tenantId, int $driverId, string $type, array $payload)
    {
        $this->tenantId = $tenantId;
        $this->driverId = $driverId;
        $this->type = $type;
        $this->payload = $payload;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("tenant.{$this->tenantId}.driver.{$this->driverId}");
    }

    public function broadcastAs()
    {
        return $this->type; // p.ej. offers.update, ride.active, ride.queued, ride.promoted
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
