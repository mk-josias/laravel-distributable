<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([AppServiceProvider::class])
    ->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'iam/api')
    ->withCommands()
    ->withMiddleware()
    ->withExceptions()
    ->create();
