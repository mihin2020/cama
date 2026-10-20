<?php

namespace App\Mail;

use App\Models\AdminUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLogin2faCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminUser $admin,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Code de connexion au back-office CAMA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-login-2fa-code',
        );
    }
}
