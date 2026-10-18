<?php

declare(strict_types=1);

namespace App\Providers;

use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Shadows\UserShadow;

final class AppServiceProvider extends ServiceProvider
{
    protected array $shadows = [UserShadow::class];
}
