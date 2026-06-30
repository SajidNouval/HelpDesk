<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * =========================================================================
 * EVENT QUEUE POSITION UPDATED - POSISI ANTREAN TIKET DIPERBARUI
 * =========================================================================
 *
 * Event ini di-trigger ketika posisi antrean tiket live chat diperbarui.
 * Event ini di-broadcast ke channel tiket untuk notifikasi real-time ke guest.
 */
class QueuePositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Membuat instance event baru.
     */
    public function __construct(
        public Ticket $ticket,
        public int $position,
        public int $estimatedWaitingMinutes
    ) {}

    /**
     * Menentukan channel untuk broadcast event.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ticket.' . $this->ticket->id),
        ];
    }

    /**
     * Menentukan nama event untuk broadcast.
     */
    public function broadcastAs(): string
    {
        return 'QueuePositionUpdated';
    }

    /**
     * Menentukan data yang di-broadcast bersama event.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'position' => $this->position,
            'estimated_waiting_minutes' => $this->estimatedWaitingMinutes,
        ];
    }
}
