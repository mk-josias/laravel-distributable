<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/** A Sanctum token per device, valid auth.tokens.ttl seconds: a new login there replaces it. */
final readonly class IssueToken
{
    /** @return array{token: string, expires_at: int} */
    public function execute(User $user, string $device): array
    {
        $user->tokens()->where('name', $device)->delete();
        $expiresAt = now()->addSeconds((int) config('auth.tokens.ttl'));

        return ['token' => $user->createToken($device, ['access'], $expiresAt)->plainTextToken, 'expires_at' => $expiresAt->getTimestamp()];
    }
}
