<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDelivered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->message->sender_id)];
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'status' => $this->message->status,
            'delivered_at' => now()->toDateTimeString(),
        ];
    }
}
