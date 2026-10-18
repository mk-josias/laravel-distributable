<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// The only route the gateway forwards without an identity.
Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
});
