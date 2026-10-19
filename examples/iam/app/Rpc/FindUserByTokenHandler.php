<?php

declare(strict_types=1);

namespace App\Rpc;

use App\Models\User;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Rpc\FindUserByToken;
use Laravel\Sanctum\PersonalAccessToken;

/** @implements Handler<FindUserByToken> */
final class FindUserByTokenHandler implements Handler
{
    /** An `access` token not expired; a logout or a new login on its device deletes it. */
    public function handle(FindUserByToken $message): ?array
    {
        $access = PersonalAccessToken::findToken($message->token);
        $user = $access?->tokenable;

        if ($access === null || ! $access->can('access') || $access->expires_at?->isFuture() !== true || ! $user instanceof User) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
