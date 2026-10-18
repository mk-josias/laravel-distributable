<?php

declare(strict_types=1);

namespace App\Providers;

use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Contracts\IamService;

final class ServicesProvider extends ServiceProvider
{
    protected array $services = [IamService::class => \App\Services\IamService::class];
}
