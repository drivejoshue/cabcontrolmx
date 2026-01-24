<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicTestEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public array $payload = [])
    {
    }

    public function broadcastOn(): Channel
    {
        // Canal público simple para pruebas
        return new Channel('public-test');
    }

    public function broadcastAs(): string
    {
        return 'public.test';
    }
}
