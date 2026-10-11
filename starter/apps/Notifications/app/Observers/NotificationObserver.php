<?php

declare(strict_types=1);

namespace Apps\Notifications\Observers;

use Apps\Notifications\Events\NotificationPushed;
use Apps\Notifications\Models\Notification;

/** After commit: a push or a mail never announces a row its transaction then rolled back. */
final class NotificationObserver
{
    public bool $afterCommit = true;

    public function created(Notification $notification): void
    {
        NotificationPushed::dispatch($notification);

        foreach ($notification->type->channels() as $channel) {
            $channel->job()::dispatch($notification);
        }
    }
}
