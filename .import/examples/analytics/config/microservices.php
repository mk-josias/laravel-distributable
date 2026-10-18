<?php

use App\Handlers\RecordMail;
use App\Handlers\RecordSignup;
use Foundation\Iam\Events\UserRegistered;
use Foundation\Notifications\Events\MailSent;

return [
    'name' => 'analytics',

    // Declared so their events are read; analytics never calls them.
    'services' => [
        'iam' => ['host' => env('IAM_HOST', 'http://127.0.0.1:8001'), 'namespace' => 'Foundation\Iam'],
        'notifications' => ['host' => env('NOTIFICATIONS_HOST', 'http://127.0.0.1:8002'), 'namespace' => 'Foundation\Notifications'],
    ],

    'events' => [
        'listen' => [
            UserRegistered::NAME => [RecordSignup::class],
            MailSent::NAME => [RecordMail::class],
        ],
    ],
];
