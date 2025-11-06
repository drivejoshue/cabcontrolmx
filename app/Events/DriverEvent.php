<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

        Log::info("🚀 DriverEvent Created", [
            'tenant' => $tenantId,
            'driver' => $driverId,
            'type' => $type,
            'channel' => "tenant.{$tenantId}.driver.{$driverId}"
        ]);
    }

    public function broadcastOn()
    {
        $channel = new PrivateChannel("tenant.{$this->tenantId}.driver.{$this->driverId}");
        
        Log::info("📡 Broadcasting to channel", [
            'channel' => $channel->name,
            'event_type' => $this->type,
            'driver' => $this->driverId
        ]);
        
        return $channel;
    }

    public function broadcastAs()
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        Log::info("📦 Event payload", $this->payload);
        return $this->payload;
    }
}