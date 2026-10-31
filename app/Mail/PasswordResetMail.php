<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $space  « l'espace assuré » ou « le back-office »
     */
    public function __construct(
        public string $fullName,
        public string $resetUrl,
        public string $space,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre mot de passe CAMA');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset');
    }
}
