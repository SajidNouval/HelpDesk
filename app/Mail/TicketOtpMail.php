<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TicketOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $type;

    public function __construct(string $otpCode, string $type)
    {
        $this->otpCode = $otpCode;
        $this->type = $type;
    }

    public function build()
    {
        $subject = $this->type === 'report'
            ? 'Kode OTP untuk Verifikasi Laporan Anda'
            : 'Kode OTP untuk Verifikasi Live Chat Anda';

        return $this->subject($subject)
            ->view('emails.ticket-otp')
            ->with([
                'otpCode' => $this->otpCode,
                'type' => $this->type,
            ]);
    }
}
