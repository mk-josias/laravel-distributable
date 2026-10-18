<?php

use App\Handlers\SendWelcome;
use Foundation\Iam\Events\UserRegistered;

return [
    'declared' => [
        'notifications' => ['path' => '.', 'namespace' => 'App'],
        'iam' => ['host' => env('IAM_HOST', 'http://127.0.0.1:8001')],
    ],

    'runs' => env('RUN_MODULES', 'notifications'),

    'events' => [
        'listen' => [UserRegistered::NAME => [SendWelcome::class]],
    ],
];
