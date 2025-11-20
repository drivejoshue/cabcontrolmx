<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $tenantId;
    public int $rideId;
    public string $name;
    public array $payload;

    public function __construct(int $tenantId, int $rideId, string $name, array $payload = [])
    {
        $this->tenantId = $tenantId;
        $this->rideId   = $rideId;
        $this->name     = $name;
        $this->payload  = $payload;
    }

    public function broadcastOn(): Channel
    {
        return new Channel("tenant.{$this->tenantId}.ride.{$this->rideId}");
    }

    public function broadcastAs(): string
    {
        return $this->name; // ej: 'ride.update', 'ride.finished'
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
