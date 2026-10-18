<?php

declare(strict_types=1);

use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

// Reached only through the gateway, which authenticated the client and signed X-Identity.
Route::prefix('v1')->group(function (): void {
    Route::get('notifications', InboxController::class);
});
