<?php

use App\Http\Controllers\Api\V1\Admin\EventModerationController;
use App\Http\Controllers\Api\V1\Admin\OrganizerModerationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OrganizerEventController;
use App\Http\Controllers\Api\V1\OrganizerProfileController;
use App\Http\Controllers\Api\V1\PreferenceController;
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

        Route::get('/me/preferences', [PreferenceController::class, 'show']);
        Route::put('/me/preferences', [PreferenceController::class, 'update']);
        Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::post('/events/{event:ulid}/bookings', [BookingController::class, 'store']);
        Route::get('/me/bookings', [BookingController::class, 'index']);
        Route::get('/me/bookings/{booking:ulid}', [BookingController::class, 'show']);
        Route::post('/me/bookings/{booking:ulid}/cancel', [BookingController::class, 'cancel']);
        Route::get('/me/notifications', [NotificationController::class, 'index']);
        Route::post('/me/notifications/{notification:ulid}/read', [NotificationController::class, 'read']);
        Route::post('/me/notifications/read-all', [NotificationController::class, 'readAll']);

        Route::post('/organizers', [OrganizerProfileController::class, 'store']);
        Route::get('/organizer/profile', [OrganizerProfileController::class, 'show']);
        Route::post('/organizer/profile', [OrganizerProfileController::class, 'store']);
        Route::put('/organizer/profile', [OrganizerProfileController::class, 'update']);
        Route::get('/organizer/events', [OrganizerEventController::class, 'index']);
        Route::post('/organizer/events', [OrganizerEventController::class, 'store']);
        Route::get('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'show']);
        Route::put('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'update']);
        Route::patch('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'update']);
        Route::post('/organizer/events/{event:ulid}/submit', [OrganizerEventController::class, 'submit']);
        Route::post('/organizer/events/{event:ulid}/publish', [OrganizerEventController::class, 'publish']);
        Route::post('/organizer/events/{event:ulid}/close-bookings', [OrganizerEventController::class, 'closeBookings']);
        Route::post('/organizer/events/{event:ulid}/cancel', [OrganizerEventController::class, 'cancel']);
        Route::get('/organizer/events/{event:ulid}/bookings', [OrganizerEventController::class, 'bookings']);

        Route::get('/admin/events/pending', [EventModerationController::class, 'pending']);
        Route::post('/admin/events/{event:ulid}/approve', [EventModerationController::class, 'approve']);
        Route::post('/admin/events/{event:ulid}/reject', [EventModerationController::class, 'reject']);
        Route::post('/admin/events/{event:ulid}/suspend', [EventModerationController::class, 'suspend']);
        Route::get('/admin/organizers/pending', [OrganizerModerationController::class, 'pending']);
        Route::post('/admin/organizers/{organizer:ulid}/approve', [OrganizerModerationController::class, 'approve']);
        Route::post('/admin/organizers/{organizer:ulid}/reject', [OrganizerModerationController::class, 'reject']);
    });
});
