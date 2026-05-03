<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('ticket.' . $this->message->ticket_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'ticket_id'   => $this->message->ticket_id,
            'message'     => $this->message->message,
            'sender_type' => $this->message->sender_type,
            'sender_name' => $this->message->sender_name ?? 'Guest',
            'created_at'  => $this->message->created_at->toDateTimeString(),
        ];
    }
}
