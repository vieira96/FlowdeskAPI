<?php

use App\Http\Controllers\Api\Team\TeamCategoryController;
use App\Http\Controllers\Api\Team\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1/teams')->name('v1.teams.')->controller(TeamController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('categories', [TeamCategoryController::class, 'index'])->name('categories.index');

    Route::middleware('admin')->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::post('{team}/agents', 'attachAgents')->name('agents.attach');
        Route::post('categories', [TeamCategoryController::class, 'store'])->name('categories.store');
    });
});
