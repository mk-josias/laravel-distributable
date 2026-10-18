<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Foundation\Iam\Auth\TrustedIdentity;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets through only a request whose X-Identity the gateway signed, and puts the user's id on it. */
final readonly class Authenticate
{
    public function __construct(private TrustedIdentity $identity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $this->identity->validate((string) $request->header((string) config('auth.gateway.header')));

        if ($userId === null) {
            throw new AuthenticationException;
        }

        $request->attributes->set('user_id', $userId);

        return $next($request);
    }
}
