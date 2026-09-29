<?php

namespace App\Mail;

use App\Models\Assure;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssurePlatformNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assure $assure,
        public string $titre,
        public string $contenu,
        public ?string $lien = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "CAMA — {$this->titre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.assure-platform-notification',
        );
    }
}
