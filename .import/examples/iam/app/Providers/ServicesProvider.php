<?php

declare(strict_types=1);

namespace App\Providers;

use Foundation\Iam\Contracts\IamService;
use Microservices\Providers\RpcServiceProvider;

final class ServicesProvider extends RpcServiceProvider
{
    protected array $services = [IamService::class => \App\Services\IamService::class];
}
