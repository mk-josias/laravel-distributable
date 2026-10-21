<?php

declare(strict_types=1);

namespace Distributable\Stream\InProcess;

use Distributable\Services\ModuleRegistry;
use Distributable\Services\Stream\TransportManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

final class StreamInProcessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(TransportManager::class, static function (TransportManager $transports): void {
            $transports->extend('in-process', static fn (Container $app): InProcessTransport => new InProcessTransport(
                $app->make(ModuleRegistry::class),
                $app,
            ));
        });
    }
}
