<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Ticket;

class TicketRejectionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function build()
    {
        return $this->subject("Tiket #{$this->ticket->id} - Staff Tidak Dapat Menerima")
            ->view('emails.ticket-rejection')
            ->with([
                'ticket' => $this->ticket,
            ]);
    }
}
