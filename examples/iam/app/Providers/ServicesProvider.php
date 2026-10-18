<?php

declare(strict_types=1);

namespace App\Providers;

use Distributable\Providers\RpcServiceProvider;
use Foundation\Iam\Contracts\IamService;

final class ServicesProvider extends RpcServiceProvider
{
    protected array $services = [IamService::class => \App\Services\IamService::class];
}
