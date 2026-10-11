<?php

declare(strict_types=1);

namespace Apps\Notifications\Enums;

use Apps\Notifications\Jobs\SendMail;

/** A channel a notification leaves by; each one is a queued job, so a slow provider never delays the inbox. */
enum Channel: string
{
    case Mail = 'mail';

    /** @return class-string */
    public function job(): string
    {
        return match ($this) {
            self::Mail => SendMail::class,
        };
    }
}
