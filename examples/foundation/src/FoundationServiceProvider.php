<?php

declare(strict_types=1);

namespace Foundation;

use Foundation\Iam\Auth\TrustedIdentity;
use Illuminate\Support\ServiceProvider;

/** Discovered in every service that requires the foundation: what they share is wired once, here. */
final class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(TrustedIdentity::class)->needs('$secret')->give(fn (): string => (string) config('auth.gateway.secret'));
    }
}
