<?php

declare(strict_types=1);

namespace RpcHttp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Microservices\Contracts\Colocation;
use Microservices\Services\Rpc\TransportManager;

final class RpcHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(TransportManager::class, static function (TransportManager $transports): void {
            $transports->extend('http', static fn (Container $app): HttpTransport => $app->make(HttpTransport::class));
        });
    }

    /** The called side of HttpTransport, one endpoint per local service: nothing unsigned reaches it. */
    public function boot(): void
    {
        $config = $this->app->make(RpcHttp::class);
        $this->app->make(Router::class)->pushMiddlewareToGroup($config->getMiddlewareGroup(), VerifyRpcSignature::class);

        foreach ($this->app->make(Colocation::class)->local() as $service) {
            Route::post(str_replace('{service}', $service, $config->getPath()), RpcController::class)
                ->middleware($config->getMiddlewareGroup())
                ->defaults('service', $service)
                ->name("microservices.rpc.{$service}");
        }
    }
}
