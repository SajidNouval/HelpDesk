<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL TICKET LOG
 * =========================================================================
 *
 * Model ini merepresentasikan tabel ticket_logs.
 *
 * Tanggung Jawab:
 * - Menyimpan log aktivitas tiket.
 * - Mencatat perubahan status dan aksi pada tiket.
 * - Mengelola relasi log dengan tiket.
 *
 * Relasi:
 * - belongsTo(Ticket): Tiket yang dicatat lognya
 */
class TicketLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'ticket_logs';

    protected $fillable = [
        'ticket_id',
        'action',
        'description',
    ];

    /**
     * Fungsi:
     * Mengambil tiket yang dicatat lognya.
     *
     * Output:
     * - Relasi belongsTo Ticket.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}