<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateReviewStatistics;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewModerationController extends Controller
{
    public function approve(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $review->update(['status' => 'approved', 'hidden_at' => null]);
        RecalculateReviewStatistics::dispatch($review->event_id, $review->organizer_profile_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function hide(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $review->update(['hidden_at' => now()]);
        RecalculateReviewStatistics::dispatch($review->event_id, $review->organizer_profile_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function destroy(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $eventId = $review->event_id;
        $organizerProfileId = $review->organizer_profile_id;
        $review->delete();

        RecalculateReviewStatistics::dispatch($eventId, $organizerProfileId);

        return response()->json(status: 204);
    }
}
