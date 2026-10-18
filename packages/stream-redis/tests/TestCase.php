<?php

declare(strict_types=1);

namespace Distributable\Stream\Redis\Tests;

use Distributable\Stream\Redis\StreamRedisServiceProvider;
use Distributable\Tests\OrdersAppTestCase as CoreTestCase;

/** The core's `orders` application, with the redis driver installed. */
abstract class TestCase extends CoreTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StreamRedisServiceProvider::class];
    }
}
