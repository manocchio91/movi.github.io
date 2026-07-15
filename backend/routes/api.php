<?php

use App\Http\Controllers\Api\V1\Admin\EventModerationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\OrganizerEventController;
use App\Http\Controllers\Api\V1\OrganizerProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event:ulid}', [EventController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/locations/search', [LocationController::class, 'search']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/organizers', [OrganizerProfileController::class, 'store']);
        Route::get('/organizer/profile', [OrganizerProfileController::class, 'show']);
        Route::get('/organizer/events', [OrganizerEventController::class, 'index']);
        Route::post('/organizer/events', [OrganizerEventController::class, 'store']);
        Route::patch('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'update']);
        Route::post('/organizer/events/{event:ulid}/submit', [OrganizerEventController::class, 'submit']);

        Route::get('/admin/events/pending', [EventModerationController::class, 'pending']);
        Route::post('/admin/events/{event:ulid}/approve', [EventModerationController::class, 'approve']);
        Route::post('/admin/events/{event:ulid}/reject', [EventModerationController::class, 'reject']);
        Route::post('/admin/events/{event:ulid}/suspend', [EventModerationController::class, 'suspend']);
    });
});
