<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AccountNotificationMail extends Mailable
{
    /** Delivery is queued by DeliverNotificationChannel; this mailable sends inside that job. */
    public function __construct(public readonly string $notificationTitle, public readonly string $notificationBody, public readonly string $recipientLocale) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-notification', text: 'mail.account-notification-text');
    }
}
