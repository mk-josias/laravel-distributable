<?php

declare(strict_types=1);

namespace Foundation\Iam\Events;

use Distributable\Attributes\Emits;
use Distributable\Events\Event;

#[Emits(UserRegistered::NAME, module: 'iam')]
final class UserRegistered extends Event
{
    public const string NAME = 'iam.user.registered';

    public function __construct(public readonly int $id) {}

    /** @param array<string, mixed> $payload */
    public static function from(array $payload): self
    {
        return new self((int) $payload['id']);
    }

    public function payload(): array
    {
        return ['id' => $this->id];
    }
}
