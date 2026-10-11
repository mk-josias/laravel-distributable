<?php

declare(strict_types=1);

namespace Apps\Analytics\Handlers;

use Apps\Analytics\Models\Mail;
use Foundation\Notifications\Events\MailSentPayload;
use Microservices\Contracts\Stream\Handler;

final class RecordMail implements Handler
{
    public function handle(string $name, array $payload): void
    {
        $mail = MailSentPayload::from($payload);

        Mail::query()->firstOrCreate(['notification_id' => $mail->notification_id], ['user_id' => $mail->user_id, 'type' => $mail->type]);
    }
}
