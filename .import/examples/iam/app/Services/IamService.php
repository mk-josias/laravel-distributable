<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Foundation\Iam\Contracts\IamService as Contract;
use Laravel\Sanctum\PersonalAccessToken;

final class IamService implements Contract
{
    /** An `access` token not expired; a logout or a new login on its device deletes it. */
    public function findUserByToken(string $token): ?array
    {
        $access = PersonalAccessToken::findToken($token);
        $user = $access?->tokenable;

        if ($access === null || ! $access->can('access') || $access->expires_at?->isFuture() !== true || ! $user instanceof User) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
