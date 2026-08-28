<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->controller(AuthController::class)->group(function (): void {
    Route::post('login', 'login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', 'me');
        Route::post('logout', 'logout');
    });
});
