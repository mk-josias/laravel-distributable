<?php

declare(strict_types=1);

use Microservices\Tests\TestCase as CoreTestCase;
use RpcHttp\Tests\TestCase as RpcHttpTestCase;
use StreamQueue\Tests\TestCase as QueueTestCase;
use StreamRedis\Tests\TestCase as RedisTestCase;

// Pest reads only this file: each package's tests get their own base class here.
uses(CoreTestCase::class)->in(__DIR__.'/../packages/core/tests');
uses(RpcHttpTestCase::class)->in(__DIR__.'/../packages/rpc-http/tests');
uses(RedisTestCase::class)->in(__DIR__.'/../packages/stream-redis/tests');
uses(QueueTestCase::class)->in(__DIR__.'/../packages/stream-queue/tests');
