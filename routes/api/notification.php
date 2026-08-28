<?php

use App\Http\Controllers\Api\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1/notifications')->controller(NotificationController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::patch('{notification}/read', 'markAsRead');
});
