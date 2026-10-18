<?php

use App\Http\Middleware\Authenticate;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([AppServiceProvider::class])
    ->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'notifications/api')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', Authenticate::class);
    })
    ->withExceptions()
    ->create();
