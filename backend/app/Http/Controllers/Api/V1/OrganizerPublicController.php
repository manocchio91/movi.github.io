<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class OrganizerPublicController extends Controller
{
    public function show(OrganizerProfile $organizer): JsonResponse
    {
        abort_unless($organizer->verification_status === 'verified', 404);
        $organizer->loadCount('events')->load(['municipality', 'reviewStatistic']);
        $public = $organizer->only([
            'ulid', 'name', 'type', 'description', 'phone', 'public_email', 'website_url',
            'instagram_url', 'facebook_url', 'tiktok_url', 'youtube_url', 'events_count',
        ]);
        $public['municipality'] = $organizer->municipality?->only(['name']);
        $public['logo_url'] = $organizer->logo_path ? Storage::disk('public')->url($organizer->logo_path) : null;
        $public['cover_url'] = $organizer->cover_path ? Storage::disk('public')->url($organizer->cover_path) : null;
        $public['review_statistic'] = $organizer->reviewStatistic;
        $customer = request()->user('sanctum');
        $public['followers_count'] = $organizer->followers()->count();
        $public['is_following'] = $customer?->followedOrganizers()
            ->whereKey($organizer->id)
            ->exists() ?? false;

        return response()->json([
            'data' => $public,
            'future_experiences' => $organizer->events()
                ->where('status', EventStatus::Published->value)
                ->where('starts_at', '>=', now())
                ->with(['categories', 'ticketTypes'])
                ->orderBy('starts_at')
                ->get(),
            'completed_experiences' => $organizer->events()
                ->where('status', EventStatus::Completed->value)
                ->latest('starts_at')
                ->limit(10)
                ->get(),
            'reviews_count' => $organizer->reviews()->where('status', 'approved')->whereNull('hidden_at')->count(),
        ]);
    }
}
