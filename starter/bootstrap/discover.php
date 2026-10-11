<?php

declare(strict_types=1);

// Runs package:discover once every package a module requires is installed: on a first install the merge plugin adds them after this hook, then runs it again.
foreach (glob(__DIR__.'/../apps/*/composer.json') ?: [] as $file) {
    foreach (array_keys(json_decode((string) file_get_contents($file), true)['require'] ?? []) as $package) {
        if (! is_dir(__DIR__.'/../vendor/'.$package)) {
            exit(0);
        }
    }
}

passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../artisan').' package:discover --ansi', $code);
exit($code);
