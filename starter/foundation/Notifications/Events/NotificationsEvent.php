<?php

declare(strict_types=1);

namespace Foundation\Notifications\Events;

/** The names of the events notifications publishes, shared with the modules that listen to them. */
enum NotificationsEvent: string
{
    case MailSent = 'notifications.mail.sent';
}
