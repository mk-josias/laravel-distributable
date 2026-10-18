<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IssueToken;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class AuthController
{
    /** The same 401 for an unknown email and a wrong password: the answer never tells which emails exist. */
    public function login(LoginRequest $request, IssueToken $issue): JsonResponse
    {
        $request->ensureIsNotRateLimited();
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $request->recordFailure();

            throw new AuthenticationException;
        }

        $request->clearFailures();

        return new JsonResponse($issue->execute($user, $request->device()), 201);
    }
}
