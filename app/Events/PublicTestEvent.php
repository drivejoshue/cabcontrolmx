<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TestEvent implements ShouldBroadcast
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new Channel('public-test');
    }

    // Opcional: nombre personalizado del evento
    public function broadcastAs()
    {
        return 'test.event';
    }

    // Opcional: datos a enviar
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString()
        ];
    }
}