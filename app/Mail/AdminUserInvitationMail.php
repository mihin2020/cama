<?php

namespace App\Mail;

use App\Models\AdminUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminUser $user,
        public string $setupUrl,
        public string $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation — accès back-office CAMA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-user-invitation',
        );
    }
}
