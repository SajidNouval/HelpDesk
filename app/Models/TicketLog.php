<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TicketLog extends Model
{
    use HasFactory, HasUlids;

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