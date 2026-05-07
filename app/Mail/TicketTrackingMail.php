<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Ticket;

class TicketTrackingMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $trackingUrl;

    public function __construct(Ticket $ticket, string $trackingUrl)
    {
        $this->ticket = $ticket;
        $this->trackingUrl = $trackingUrl;
    }

    public function build()
    {
        return $this->subject("Link Tracking Tiket #{$this->ticket->id}")
            ->view('emails.ticket-tracking')
            ->with([
                'ticket' => $this->ticket,
                'trackingUrl' => $this->trackingUrl,
            ]);
    }
}
