<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The journal of mails sent, one row per notification: a replayed event finds its row and changes nothing.
 *
 * @property int $notification_id
 * @property int $user_id
 * @property string $type
 * @property Carbon $created_at
 */
final class Mail extends Model
{
    protected $primaryKey = 'notification_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['notification_id', 'user_id', 'type', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
