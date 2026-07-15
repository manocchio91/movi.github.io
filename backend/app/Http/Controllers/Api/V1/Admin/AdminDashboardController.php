<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => User::query()->count(),
                'customers' => User::query()->where('role', UserRole::Customer->value)->count(),
                'organizers' => User::query()->where('role', UserRole::Organizer->value)->count(),
                'pending_organizers' => OrganizerProfile::query()->where('verification_status', 'pending')->count(),
                'published_events' => Event::query()->where('status', EventStatus::Published->value)->count(),
                'suspended_events' => Event::query()->where('status', EventStatus::Suspended->value)->count(),
                'archived_events' => Event::query()->where('status', EventStatus::Archived->value)->count(),
                'cancelled_events' => Event::query()->where('status', EventStatus::Cancelled->value)->count(),
                'open_review_reports' => ReviewReport::query()->where('status', 'open')->count(),
            ],
        ]);
    }
}
