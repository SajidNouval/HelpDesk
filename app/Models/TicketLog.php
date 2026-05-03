<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketLog extends Model
{
    use HasFactory;

    protected $table = 'ticket_logs';

    protected $fillable = [
        'ticket_id',
        'action',
        'description',
    ];

    // Relasi
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}