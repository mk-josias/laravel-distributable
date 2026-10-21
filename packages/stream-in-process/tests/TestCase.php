<?php

declare(strict_types=1);

namespace Distributable\Stream\InProcess\Tests;

use Distributable\Stream\InProcess\StreamInProcessServiceProvider;
use Distributable\Tests\ModuleAppTestCase as CoreTestCase;

/** The core's application of three modules, with the in-process driver installed. */
abstract class TestCase extends CoreTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), StreamInProcessServiceProvider::class];
    }
}
