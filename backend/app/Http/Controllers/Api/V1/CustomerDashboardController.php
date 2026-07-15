<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class CustomerDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'data' => [
                'recommended_events' => Event::query()
                    ->with(['categories', 'municipality', 'organizerProfile'])
                    ->where('status', EventStatus::Published)
                    ->orderBy('starts_at')
                    ->limit(6)
                    ->get(),
                'bookings_count' => $user->bookings()->count(),
                'favorites_count' => $user->favorites()->count(),
                'notifications_unread' => $user->notifications()->where('read', false)->count(),
                'preferences' => $user->preferredCategories()->get(),
            ],
        ]);
    }
}
