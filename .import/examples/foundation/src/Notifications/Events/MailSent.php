<?php

declare(strict_types=1);

namespace Foundation\Notifications\Events;

use Microservices\Events\Event;

final class MailSent extends Event
{
    public const string NAME = 'notifications.mail.sent';

    public function __construct(
        public readonly int $notificationId,
        public readonly int $userId,
        public readonly string $type,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function from(array $payload): self
    {
        return new self((int) $payload['notification_id'], (int) $payload['user_id'], (string) $payload['type']);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function payload(): array
    {
        return ['notification_id' => $this->notificationId, 'user_id' => $this->userId, 'type' => $this->type];
    }
}
