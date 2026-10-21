<?php

return [
    // The database.redis connection the streams use; null: the `default` one.
    'connection' => env('DISTRIBUTABLE_STREAM_CONNECTION'),

    // How long a read waits for an entry, in ms.
    'block' => 5_000,

    // Entries per read.
    'count' => 50,

    // After how long the entries a dead consumer left pending are taken back, in ms.
    'claim_after' => 60_000,

    // block: a failed entry is retried before later ones. skip: later ones go on, the failed one is dropped.
    'on_failure' => 'block',
];
