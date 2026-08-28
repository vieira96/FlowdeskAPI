<?php

use App\Http\Controllers\Api\Ticket\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1/tickets')->controller(TicketController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('{ticket}', 'show');
});
