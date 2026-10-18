<?php

use App\Handlers\RecordMail;
use App\Handlers\RecordSignup;
use Foundation\Iam\Events\UserRegistered;
use Foundation\Notifications\Events\MailSent;

return [
    'declared' => [
        'analytics' => ['path' => '.', 'namespace' => 'App'],
        // Declared so their events are read; analytics never calls them.
        'iam' => ['host' => env('IAM_HOST', 'http://127.0.0.1:8001')],
        'notifications' => ['host' => env('NOTIFICATIONS_HOST', 'http://127.0.0.1:8002')],
    ],

    'runs' => env('RUN_MODULES', 'analytics'),

    'events' => [
        'listen' => [
            UserRegistered::NAME => [RecordSignup::class],
            MailSent::NAME => [RecordMail::class],
        ],
    ],
];
