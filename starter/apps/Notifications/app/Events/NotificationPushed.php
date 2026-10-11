<?php

declare(strict_types=1);

namespace Apps\Notifications\Events;

use Apps\Notifications\Http\Resources\NotificationResource;
use Apps\Notifications\Models\Notification;
use Foundation\Common\Broadcasting\PrivateUserChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** The live push of a row already written: whoever was not connected finds it in the inbox, in the same shape. */
final class NotificationPushed implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly Notification $notification) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateUserChannel($this->notification->recipient_user_id)];
    }

    public function broadcastAs(): string
    {
        return $this->notification->type->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return NotificationResource::make($this->notification)->resolve();
    }
}
