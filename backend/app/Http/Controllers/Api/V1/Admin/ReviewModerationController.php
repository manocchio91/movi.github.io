<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateReviewStatistics;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

class ReviewModerationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $review->update(['status' => 'approved', 'hidden_at' => null]);
        $this->audit->record('admin.review.approved', $review);
        RecalculateReviewStatistics::dispatch($review->event_id, $review->organizer_profile_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function hide(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $review->update(['hidden_at' => now()]);
        $this->audit->record('admin.review.hidden', $review);
        RecalculateReviewStatistics::dispatch($review->event_id, $review->organizer_profile_id);

        return response()->json(['data' => $review->fresh()]);
    }

    public function destroy(Review $review): JsonResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $eventId = $review->event_id;
        $organizerProfileId = $review->organizer_profile_id;
        $this->audit->record('admin.review.deleted', $review, ['event_id' => $eventId, 'organizer_profile_id' => $organizerProfileId]);
        $review->delete();

        RecalculateReviewStatistics::dispatch($eventId, $organizerProfileId);

        return response()->json(status: 204);
    }
}
