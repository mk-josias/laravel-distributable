<?php

declare(strict_types=1);

namespace StreamRedis\Tests;

use Distributable\Tests\Microservices\TestCase as CoreTestCase;
use StreamRedis\StreamRedisServiceProvider;

/** The core's `orders` application, with the redis driver installed. */
abstract class TestCase extends CoreTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StreamRedisServiceProvider::class];
    }
}
