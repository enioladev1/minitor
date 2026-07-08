<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent by the "Send test email" button in Settings > Notifications so an
 * operator can confirm their provider credentials work before relying on
 * them for real issue alerts.
 */
class TestNotificationMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MINITOR test notification',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test-notification');
    }
}
