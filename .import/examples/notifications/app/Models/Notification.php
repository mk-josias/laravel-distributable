<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 */
final class Notification extends Model
{
    protected $fillable = ['user_id', 'type'];
}
