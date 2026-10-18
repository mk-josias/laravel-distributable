<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Distributable\Contracts\Stream\Bus;
use Foundation\Iam\Events\UserRegistered;
use Illuminate\Support\Facades\DB;

final readonly class RegisterUser
{
    public function __construct(private Bus $bus) {}

    /** No token here: the client logs in with POST /iam/api/v1/login. */
    public function execute(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);

            $this->bus->emit(new UserRegistered($user->id));

            return $user;
        });
    }
}
