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
 * EVENT TICKET CLOSED - TIKET DITUTUP
 * =========================================================================
 *
 * Event ini di-trigger ketika tiket ditutup.
 * Event ini di-broadcast ke channel tiket untuk notifikasi real-time.
 */
class TicketClosed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Fungsi:
     * Membuat instance event baru dengan data tiket.
     */
    public function __construct(public Ticket $ticket) {}

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
            new Channel('ticket.' . $this->ticket->id),
        ];
    }

    /**
     * Fungsi:
     * Menentukan nama event untuk broadcast.
     *
     * Output:
     * - String nama event 'TicketClosed'.
     */
    public function broadcastAs(): string
    {
        return 'TicketClosed';
    }

    /**
     * Fungsi:
     * Menentukan data yang di-broadcast bersama event.
     *
     * Output:
     * - Array data tiket untuk broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
        ];
    }
}
