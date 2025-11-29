<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicTestEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $message;
    public array $meta;

    public function __construct(string $message, array $meta = [])
    {
        $this->message = $message;
        $this->meta    = $meta;
    }

    public function broadcastOn()
    {
        // 👈 canal público para debug, sin "private-"
        return new Channel('public-test');
    }

    public function broadcastAs()
    {
        // nombre del evento que vamos a escuchar del lado JS
        return 'debug.test';
    }
}
