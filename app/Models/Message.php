<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL MESSAGE
 * =========================================================================
 *
 * Model ini merepresentasikan tabel messages.
 *
 * Tanggung Jawab:
 * - Menyimpan pesan dalam tiket.
 * - Mengelola relasi pesan dengan tiket dan sender.
 * - Menyimpan status read/unread pesan.
 *
 * Relasi:
 * - belongsTo(Ticket): Tiket tempat pesan berada
 * - belongsTo(User): Pengirim pesan
 */
class Message extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'messages';

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Fungsi:
     * Mengambil tiket tempat pesan berada.
     *
     * Output:
     * - Relasi belongsTo Ticket.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Fungsi:
     * Mengambil pengirim pesan.
     *
     * Output:
     * - Relasi belongsTo User.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}   