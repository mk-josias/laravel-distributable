<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Microservices\Contracts\Shadows\Shadowed;
use Microservices\Traits\ShadowSource;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
final class User extends Model implements Shadowed
{
    use HasApiTokens;
    use ShadowSource;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    /** @var list<string> */
    protected array $shadowed = ['name', 'email'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
