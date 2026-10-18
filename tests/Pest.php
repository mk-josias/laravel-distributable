<?php

declare(strict_types=1);

// Every test file declares its own base case: the pure-unit ones use TestCase, the ones that
// need a booted application use ModuleAppTestCase. No folder-wide rule — a file-level uses()
// would silently conflict with it.

use Distributable\Tests\Microservices\TestCase as MicroservicesTestCase;
use RpcHttp\Tests\TestCase as RpcHttpTestCase;
use StreamQueue\Tests\TestCase as QueueTestCase;
use StreamRedis\Tests\TestCase as RedisTestCase;

// The imported packages keep one base class per folder, as they had; packages/core/tests is not among them.
uses(MicroservicesTestCase::class)->in(__DIR__.'/../packages/microservices/tests');
uses(RpcHttpTestCase::class)->in(__DIR__.'/../packages/rpc-http/tests');
uses(RedisTestCase::class)->in(__DIR__.'/../packages/stream-redis/tests');
uses(QueueTestCase::class)->in(__DIR__.'/../packages/stream-queue/tests');
