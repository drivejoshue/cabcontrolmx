<?php

namespace App\Events;

use App\Models\DriverMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class DriverMessageCreated implements ShouldBroadcast
{
    public $msg;

    public function __construct(DriverMessage $msg)
    {
        $this->msg = $msg;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("tenant.{$this->msg->tenant_id}.driver.{$this->msg->driver_id}");
    }

    public function broadcastAs()
    {
        return 'driver.message.new';
    }

    public function broadcastWith()
    {
        return [
            'id'          => $this->msg->id,
            'driver_id'   => $this->msg->driver_id,
            'sender_type' => $this->msg->sender_type,
            'text'        => $this->msg->text,
            'created_at'  => $this->msg->created_at->toDateTimeString(),
        ];
    }
}
