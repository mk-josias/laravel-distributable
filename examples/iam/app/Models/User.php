<?php

declare(strict_types=1);

namespace App\Models;

use Distributable\Attributes\ShadowFields;
use Distributable\Contracts\Shadows\Shadowed;
use Distributable\Traits\ShadowSource;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[ShadowFields('name', 'email')]
final class User extends Model implements Shadowed
{
    use HasApiTokens;
    use ShadowSource;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
