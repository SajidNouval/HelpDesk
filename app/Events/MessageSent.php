<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * =========================================================================
 * EVENT MESSAGE SENT - PESAN TIKET TERKIRIM
 * =========================================================================
 *
 * Event ini di-trigger ketika pesan baru dikirim dalam tiket.
 * Event ini di-broadcast ke channel tiket terkait untuk real-time update.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Fungsi:
     * Membuat instance event baru dengan data pesan.
     */
    public function __construct(public Message $message) {}

    /**
     * Fungsi:
     * Menentukan channel untuk broadcast event.
     *
     * Output:
     * - Array channel untuk broadcast ke tiket terkait.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ticket.' . $this->message->ticket_id),
        ];
    }

    /**
     * Fungsi:
     * Menentukan nama event untuk broadcast.
     *
     * Output:
     * - String nama event 'MessageSent'.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Fungsi:
     * Menentukan data yang di-broadcast bersama event.
     *
     * Output:
     * - Array data pesan untuk broadcast.
     */
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
