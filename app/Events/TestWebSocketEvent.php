<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * =========================================================================
 * EVENT TEST WEBSOCKET - EVENT UNTUK TESTING WEBSOCKET
 * =========================================================================
 *
 * Event ini digunakan untuk testing koneksi WebSocket.
 * Event ini di-broadcast ke channel test-channel.
 */
class TestWebSocketEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Fungsi:
     * Membuat instance event baru dengan pesan test.
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Fungsi:
     * Menentukan channel untuk broadcast event.
     *
     * Output:
     * - Array channel test untuk broadcast.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('test-channel'),
        ];
    }

    /**
     * Fungsi:
     * Menentukan nama event untuk broadcast.
     *
     * Output:
     * - String nama event 'test-event'.
     */
    public function broadcastAs(): string
    {
        return 'test-event';
    }
}
