<?php

declare(strict_types=1);

namespace Apps\Notifications\Mail;

use Apps\Notifications\Models\Notification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class NotificationMail extends Mailable
{
    public function __construct(public readonly Notification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->type->title($this->notification));
    }

    public function content(): Content
    {
        return new Content(htmlString: e($this->notification->type->title($this->notification)));
    }
}
