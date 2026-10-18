<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The journal of registrations, one row per user: a replayed event finds its row and changes nothing.
 *
 * @property int $user_id
 * @property Carbon $created_at
 */
final class Signup extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
