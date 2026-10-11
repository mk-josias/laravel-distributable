<?php

declare(strict_types=1);

namespace Apps\Notifications\Enums;

use Apps\Notifications\Models\Notification;

/** What a notification is about: the client routes on it, the server renders its title and picks its channels from it. */
enum NotificationType: string
{
    case Welcome = 'user.welcome';

    public function title(Notification $notification): string
    {
        return match ($this) {
            self::Welcome => __('notifications::messages.welcome', ['name' => $notification->recipient->name ?? '']),
        };
    }

    /** @return list<Channel> where it goes beyond the inbox and the live push, which every notification gets */
    public function channels(): array
    {
        return match ($this) {
            self::Welcome => [Channel::Mail],
        };
    }
}
