<?php

namespace App\Mail;

use App\Models\Assure;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Prévient l'ANCIENNE adresse qu'elle n'est plus associée au compte. */
class AssureEmailChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assure $assure,
        public string $newEmailMasked,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre adresse e-mail CAMA a été modifiée');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.assure-email-changed');
    }
}
