<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Models\Notification;
use Distributable\Contracts\Stream\Bus;
use Distributable\Contracts\Stream\Handler;
use Foundation\Iam\Events\UserRegistered;
use Foundation\Iam\Shadows\UserShadow;
use Foundation\Notifications\Events\MailSent;
use Illuminate\Support\Facades\Mail;

/** The address comes from notifications' copy of iam's users, filled by the event iam emitted just before. */
final readonly class SendWelcome implements Handler
{
    public function __construct(private Bus $bus) {}

    public function handle(string $name, array $payload): void
    {
        $user = UserShadow::query()->findOrFail(UserRegistered::from($payload)->id);
        $notification = Notification::query()->create(['user_id' => $user->id, 'type' => 'welcome']);

        Mail::raw("Welcome, {$user->name}.", fn ($mail) => $mail->to($user->email)->subject('Welcome'));

        $this->bus->emit(new MailSent($notification->id, $user->id, $notification->type));
    }
}
