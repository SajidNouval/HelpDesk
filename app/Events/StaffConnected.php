<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * =========================================================================
 * EVENT STAFF CONNECTED - STAFF TERHUBUNG KE TIKET
 * =========================================================================
 *
 * Event ini di-trigger ketika staff terhubung ke tiket.
 * Event ini di-broadcast ke channel tiket untuk notifikasi real-time.
 */
class StaffConnected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Fungsi:
     * Membuat instance event baru dengan data tiket dan staff.
     */
    public function __construct(public Ticket $ticket, public User $staff) {}

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
     * - String nama event 'StaffConnected'.
     */
    public function broadcastAs(): string
    {
        return 'StaffConnected';
    }

    /**
     * Fungsi:
     * Menentukan data yang di-broadcast bersama event.
     *
     * Output:
     * - Array data tiket dan staff untuk broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id'  => $this->ticket->id,
            'staff_id'   => $this->staff->id,
            'staff_name' => $this->staff->name,
        ];
    }
}
