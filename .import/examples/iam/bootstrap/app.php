<?php

use App\Providers\ServicesProvider;
use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([ServicesProvider::class])
    ->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'iam/api')
    ->withCommands()
    ->withMiddleware()
    ->withExceptions()
    ->create();
