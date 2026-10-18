<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Models\Mail;
use Foundation\Notifications\Events\MailSent;
use Microservices\Contracts\Stream\Handler;

final class RecordMail implements Handler
{
    public function handle(string $name, array $payload): void
    {
        $mail = MailSent::from($payload);

        Mail::query()->insertOrIgnore([
            'notification_id' => $mail->notificationId,
            'user_id' => $mail->userId,
            'type' => $mail->type,
            'created_at' => now(),
        ]);
    }
}
