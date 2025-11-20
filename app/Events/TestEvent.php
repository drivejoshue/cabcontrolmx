<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class TestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    // Canal público de prueba
    public function broadcastOn(): Channel
    {
        return new Channel('public-test');
    }

    // Para que en JS puedas hacer .listen('.TestEvent')
    public function broadcastAs(): string
    {
        return 'TestEvent';
    }

    // Payload que recibirá el front
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
