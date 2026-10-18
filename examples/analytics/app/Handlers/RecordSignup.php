<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Models\Signup;
use Distributable\Contracts\Stream\Handler;
use Foundation\Iam\Events\UserRegistered;

final class RecordSignup implements Handler
{
    public function handle(string $name, array $payload): void
    {
        Signup::query()->insertOrIgnore(['user_id' => UserRegistered::from($payload)->id, 'created_at' => now()]);
    }
}
