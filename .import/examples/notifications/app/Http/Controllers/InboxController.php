<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class InboxController
{
    /** @return Collection<int, string> */
    public function __invoke(Request $request): Collection
    {
        return Notification::query()->where('user_id', $request->attributes->getInt('user_id'))->pluck('type');
    }
}
