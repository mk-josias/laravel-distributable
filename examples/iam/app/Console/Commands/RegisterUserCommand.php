<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RegisterUser;
use Illuminate\Console\Command;

final class RegisterUserCommand extends Command
{
    protected $signature = 'users:register {name} {email} {password}';

    protected $description = 'Register a user; the client then logs in through the gateway';

    public function handle(RegisterUser $register): int
    {
        $user = $register->execute((string) $this->argument('name'), (string) $this->argument('email'), (string) $this->argument('password'));

        $this->components->info("User {$user->id} registered.");

        return self::SUCCESS;
    }
}
