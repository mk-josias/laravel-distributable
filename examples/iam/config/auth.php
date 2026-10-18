<?php

return [
    'tokens' => [
        // Seconds a token lives; a new login on the same device replaces it.
        'ttl' => (int) env('AUTH_TOKEN_TTL', 3600),
    ],
];
