<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // para pruebas sin colas
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public int $tenantId,
        public array $payload
    ) {}

    public function broadcastOn(): Channel
    {
        // en producción usaremos canal privado, para la demo dejamos público
        return new Channel("driver.location.{$this->tenantId}");
        // Para privado sería: new PrivateChannel("private.driver.location.{$this->tenantId}")
    }

    public function broadcastAs(): string
    {
        return 'LocationUpdated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
