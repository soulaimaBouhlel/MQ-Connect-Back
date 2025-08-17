<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageIds;
    public $readerId;

    public function __construct(array $messageIds, int $readerId)
    {
        $this->messageIds = $messageIds;
        $this->readerId = $readerId;
    }

    public function broadcastOn(): array
    {
        // Broadcast to sender(s)
        return [new PrivateChannel('chat.' . $this->readerId)];
    }

    public function broadcastWith()
    {
        return [
            'message_ids' => $this->messageIds,
        ];
    }
}
