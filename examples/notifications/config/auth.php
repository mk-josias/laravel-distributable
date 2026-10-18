<?php

return [
    // The gateway checks the client's token and sends X-Identity, its claims signed with this secret: a service reads only that header.
    'gateway' => [
        'header' => 'X-Identity',
        'secret' => env('GATEWAY_SECRET'),
    ],
];
