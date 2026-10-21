<?php

declare(strict_types=1);

// Every test file declares its own base case: the pure-unit ones use TestCase, the ones that
// need a booted application use ModuleAppTestCase. No folder-wide rule — a file-level uses()
// would silently conflict with it.

use Distributable\RpcHttp\Tests\TestCase as RpcHttpTestCase;
use Distributable\Stream\InProcess\Tests\TestCase as InProcessTestCase;
use Distributable\Stream\Redis\Tests\TestCase as RedisTestCase;

// The imported packages keep one base class per folder, as they had; packages/core/tests is not among them.
uses(RpcHttpTestCase::class)->in(__DIR__.'/../packages/rpc-http/tests/Feature');
uses(RedisTestCase::class)->in(__DIR__.'/../packages/stream-redis/tests');
uses(InProcessTestCase::class)->in(__DIR__.'/../packages/stream-in-process/tests');
