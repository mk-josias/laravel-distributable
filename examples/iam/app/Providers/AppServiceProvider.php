<?php

declare(strict_types=1);

namespace App\Providers;

use App\Rpc\FindUserByTokenHandler;
use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Rpc\FindUserByToken;

final class AppServiceProvider extends ServiceProvider
{
    protected array $rpcHandlers = [FindUserByToken::class => FindUserByTokenHandler::class];
}
