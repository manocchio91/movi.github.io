<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminOrganizerInsightController;
use App\Http\Controllers\Api\V1\Admin\AdminPlatformOverviewController;
use App\Http\Controllers\Api\V1\Admin\AdminRankingController;
use App\Http\Controllers\Api\V1\Admin\EventModerationController;
use App\Http\Controllers\Api\V1\Admin\OrganizerModerationController;
use App\Http\Controllers\Api\V1\Admin\ReviewModerationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerDashboardController;
use App\Http\Controllers\Api\V1\CustomerSuccessfulEventController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OrganizerAnalyticsController;
use App\Http\Controllers\Api\V1\OrganizerDashboardController;
use App\Http\Controllers\Api\V1\OrganizerEventController;
use App\Http\Controllers\Api\V1\OrganizerOpportunityController;
use App\Http\Controllers\Api\V1\OrganizerPlanningController;
use App\Http\Controllers\Api\V1\OrganizerPublicController;
use App\Http\Controllers\Api\V1\OrganizerReviewController;
use App\Http\Controllers\Api\V1\OrganizerProfileController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event:ulid}', [EventController::class, 'show']);
    Route::get('/events/{event:ulid}/reviews', [ReviewController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/locations/search', [LocationController::class, 'search']);
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/organizers/{organizer:ulid}', [OrganizerPublicController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::middleware('role:customer')->group(function (): void {
            Route::get('/customer/dashboard', [CustomerDashboardController::class, 'show']);
            Route::get('/customer/successful-events', [CustomerSuccessfulEventController::class, 'index']);
            Route::get('/customer/successful-events/{event:ulid}', [CustomerSuccessfulEventController::class, 'show']);
            Route::get('/customer/successful-events/{event:ulid}/next-editions', [CustomerSuccessfulEventController::class, 'nextEditionsEndpoint']);
            Route::get('/customer/successful-events/{event:ulid}/similar', [CustomerSuccessfulEventController::class, 'similar']);
            Route::post('/customer/successful-events/{event:ulid}/notify-similar', [CustomerSuccessfulEventController::class, 'notifySimilar']);
            Route::delete('/customer/successful-events/{event:ulid}/notify-similar', [CustomerSuccessfulEventController::class, 'deleteNotifySimilar']);
            Route::get('/me/preferences', [PreferenceController::class, 'show']);
            Route::put('/me/preferences', [PreferenceController::class, 'update']);
            Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'show']);
            Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);
            Route::post('/events/{event:ulid}/bookings', [BookingController::class, 'store']);
            Route::post('/events/{event:ulid}/reviews', [ReviewController::class, 'store']);
            Route::get('/me/bookings', [BookingController::class, 'index']);
            Route::get('/me/bookings/{booking:ulid}', [BookingController::class, 'show']);
            Route::post('/me/bookings/{booking:ulid}/cancel', [BookingController::class, 'cancel']);
            Route::get('/me/favorites', [FavoriteController::class, 'index']);
            Route::post('/me/favorites/{event:ulid}', [FavoriteController::class, 'store']);
            Route::delete('/me/favorites/{event:ulid}', [FavoriteController::class, 'destroy']);
            Route::get('/me/notifications', [NotificationController::class, 'index']);
            Route::post('/me/notifications/{notification:ulid}/read', [NotificationController::class, 'read']);
            Route::post('/me/notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('/organizers/{organizer:ulid}/follow', [OrganizerPublicController::class, 'follow']);
            Route::delete('/organizers/{organizer:ulid}/follow', [OrganizerPublicController::class, 'unfollow']);
        });

        Route::middleware('role:organizer')->group(function (): void {
            Route::get('/organizer/profile', [OrganizerProfileController::class, 'show']);
            Route::post('/organizer/profile', [OrganizerProfileController::class, 'store']);
            Route::put('/organizer/profile', [OrganizerProfileController::class, 'update']);

            Route::middleware('organizer.approved')->group(function (): void {
                Route::get('/organizer/dashboard', [OrganizerDashboardController::class, 'show']);
                Route::get('/organizer/reviews', [OrganizerReviewController::class, 'index']);
                Route::get('/organizer/statistics', [OrganizerAnalyticsController::class, 'statistics']);
                Route::get('/organizer/analytics', [OrganizerAnalyticsController::class, 'analytics']);
                Route::get('/organizer/followers', [OrganizerAnalyticsController::class, 'followers']);
                Route::get('/organizer/categories', [OrganizerAnalyticsController::class, 'categories']);
                Route::get('/organizer/opportunities', [OrganizerOpportunityController::class, 'index']);
                Route::post('/organizer/opportunities/generate', [OrganizerOpportunityController::class, 'generate']);
                Route::get('/organizer/opportunities/top-events', [OrganizerOpportunityController::class, 'topEvents']);
                Route::post('/organizer/opportunities/{suggestion:ulid}/use', [OrganizerOpportunityController::class, 'useSuggestion']);
                Route::post('/organizer/top-events/{event:ulid}/inspire', [OrganizerOpportunityController::class, 'inspire']);
                Route::post('/organizer/campaigns', [OrganizerAnalyticsController::class, 'storeCampaign']);
                Route::get('/organizer/campaigns', [OrganizerAnalyticsController::class, 'campaigns']);
                Route::get('/organizer/calendar', [OrganizerPlanningController::class, 'calendar']);
                Route::get('/organizer/calendar/availability', [OrganizerPlanningController::class, 'availability']);
                Route::get('/organizer/calendar/events', [OrganizerPlanningController::class, 'events']);
                Route::get('/organizer/map', [OrganizerPlanningController::class, 'map']);
                Route::get('/organizer/events', [OrganizerEventController::class, 'index']);
                Route::post('/organizer/events', [OrganizerEventController::class, 'store']);
                Route::get('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'show']);
                Route::get('/organizer/events/{event:ulid}/summary', [OrganizerEventController::class, 'summary']);
                Route::get('/organizer/events/{event:ulid}/participants', [OrganizerEventController::class, 'participants']);
                Route::get('/organizer/events/{event:ulid}/reviews', [OrganizerEventController::class, 'reviews']);
                Route::put('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'update']);
                Route::patch('/organizer/events/{event:ulid}', [OrganizerEventController::class, 'update']);
                Route::post('/organizer/events/{event:ulid}/publish', [OrganizerEventController::class, 'publish']);
                Route::post('/organizer/events/{event:ulid}/close-bookings', [OrganizerEventController::class, 'closeBookings']);
                Route::post('/organizer/events/{event:ulid}/cancel', [OrganizerEventController::class, 'cancel']);
                Route::get('/organizer/events/{event:ulid}/bookings', [OrganizerEventController::class, 'bookings']);
                Route::get('/organizer/reviews/events', [OrganizerReviewController::class, 'events']);
                Route::get('/organizer/reviews/events/{event:ulid}', [OrganizerReviewController::class, 'event']);
            });
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::get('/admin/dashboard', [AdminDashboardController::class, 'show']);
            Route::get('/admin/overview', [AdminPlatformOverviewController::class, 'show']);
            Route::get('/admin/organizers', [OrganizerModerationController::class, 'index']);
            Route::get('/admin/organizers/pending', [OrganizerModerationController::class, 'pending']);
            Route::get('/admin/events', [EventModerationController::class, 'index']);
            Route::get('/admin/events/{event:ulid}', [EventModerationController::class, 'show']);
            Route::post('/admin/events/{event:ulid}/suspend', [EventModerationController::class, 'suspend']);
            Route::post('/admin/events/{event:ulid}/archive', [EventModerationController::class, 'archive']);
            Route::get('/admin/organizers/{organizer:ulid}', [AdminOrganizerInsightController::class, 'show']);
            Route::get('/admin/organizers/{organizer:ulid}/events', [AdminOrganizerInsightController::class, 'events']);
            Route::get('/admin/organizers/{organizer:ulid}/reviews', [AdminOrganizerInsightController::class, 'reviews']);
            Route::get('/admin/organizers/{organizer:ulid}/bookings', [AdminOrganizerInsightController::class, 'bookings']);
            Route::get('/admin/organizers/{organizer:ulid}/cancellations', [AdminOrganizerInsightController::class, 'cancellations']);
            Route::get('/admin/rankings/events', [AdminRankingController::class, 'events']);
            Route::get('/admin/rankings/organizers', [AdminRankingController::class, 'organizers']);
            Route::get('/admin/rankings/events/{event:ulid}/breakdown', [AdminRankingController::class, 'eventBreakdown']);
            Route::post('/admin/organizers/{organizer:ulid}/approve', [OrganizerModerationController::class, 'approve']);
            Route::post('/admin/organizers/{organizer:ulid}/reject', [OrganizerModerationController::class, 'reject']);
            Route::post('/admin/reviews/{review:ulid}/approve', [ReviewModerationController::class, 'approve']);
            Route::post('/admin/reviews/{review:ulid}/hide', [ReviewModerationController::class, 'hide']);
            Route::delete('/admin/reviews/{review:ulid}', [ReviewModerationController::class, 'destroy']);
        });
    });

    Route::post('/reviews/{review:ulid}/report', [ReviewController::class, 'report'])->middleware('auth:sanctum');
});
