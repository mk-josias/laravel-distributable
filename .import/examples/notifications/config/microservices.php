<?php

use App\Handlers\SendWelcome;
use Foundation\Iam\Events\UserRegistered;
use Foundation\Iam\Shadows\UserShadow;

return [
    'name' => 'notifications',

    'services' => [
        'iam' => ['host' => env('IAM_HOST', 'http://127.0.0.1:8001'), 'namespace' => 'Foundation\Iam'],
    ],

    'shadows' => [UserShadow::class],

    'events' => [
        'listen' => [UserRegistered::NAME => [SendWelcome::class]],
    ],
];
