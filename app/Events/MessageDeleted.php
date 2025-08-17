<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $senderId;
    public $receiverId;

    public function __construct(int $messageId, int $senderId, int $receiverId)
    {
        $this->messageId = $messageId;
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->senderId),
            new PrivateChannel('chat.' . $this->receiverId),
        ];
    }

    public function broadcastWith()
    {
        return ['id' => $this->messageId];
    }
}
