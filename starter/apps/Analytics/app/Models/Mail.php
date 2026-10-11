<?php

declare(strict_types=1);

namespace Apps\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $notification_id
 * @property int $user_id
 * @property string $type
 * @property Carbon $created_at
 */
final class Mail extends Model
{
    protected $table = 'analytics_mails';

    public const UPDATED_AT = null;

    protected $fillable = ['notification_id', 'user_id', 'type'];
}
