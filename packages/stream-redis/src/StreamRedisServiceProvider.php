<?php

declare(strict_types=1);

namespace Distributable\Stream\Redis;

use Distributable\Services\Stream\TransportManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Support\ServiceProvider;

final class StreamRedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(TransportManager::class, static function (TransportManager $transports): void {
            $transports->extend('redis', static fn (Container $app, array $options, string $stream): RedisStreamTransport => new RedisStreamTransport(
                $app->make(Redis::class),
                new RedisStream($app->make(Repository::class), $stream),
            ));
        });
    }
}
