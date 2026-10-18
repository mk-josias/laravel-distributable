<?php

declare(strict_types=1);

namespace Foundation\Iam\Shadows;

use Microservices\Models\ShadowModel;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
class UserShadow extends ShadowModel
{
    public static function owner(): string
    {
        return 'iam';
    }

    public static function sourceTable(): string
    {
        return 'users';
    }
}
