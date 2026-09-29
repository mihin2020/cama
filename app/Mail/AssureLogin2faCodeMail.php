<?php

namespace App\Mail;

use App\Models\Assure;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssureLogin2faCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assure $assure,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Code de connexion CAMA : {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.assure-login-2fa-code',
        );
    }
}
