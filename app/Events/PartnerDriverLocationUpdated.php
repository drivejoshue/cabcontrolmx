<?php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartnerDriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $partnerId,
        public array $payload
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel("partner.{$this->partnerId}.drivers");
    }

    public function broadcastAs(): string
    {
        return 'LocationUpdated'; // igual que tu listener actual
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
