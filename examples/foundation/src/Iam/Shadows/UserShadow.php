<?php

declare(strict_types=1);

namespace Foundation\Iam\Shadows;

use Distributable\Attributes\Shadow;
use Distributable\Models\ShadowModel;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
#[Shadow(module: 'iam', table: 'users', migrations: [__DIR__.'/../database/shadows/users'])]
class UserShadow extends ShadowModel {}
