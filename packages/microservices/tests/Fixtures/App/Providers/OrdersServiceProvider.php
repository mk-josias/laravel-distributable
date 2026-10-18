<?php

declare(strict_types=1);

namespace Distributable\Tests\Microservices\Fixtures\App\Providers;

use Distributable\Providers\RpcServiceProvider;
use Distributable\Tests\Microservices\Fixtures\App\Contracts\OrdersService as OrdersContract;
use Distributable\Tests\Microservices\Fixtures\App\Services\OrdersService;
use Distributable\Tests\Microservices\Fixtures\Billing\Contracts\BillingService;
use Distributable\Tests\Microservices\Fixtures\Billing\Services\BillingRpcService;

final class OrdersServiceProvider extends RpcServiceProvider
{
    protected array $rpc = [
        BillingService::class => BillingRpcService::class,
    ];

    protected array $services = [
        OrdersContract::class => OrdersService::class,
    ];
}
