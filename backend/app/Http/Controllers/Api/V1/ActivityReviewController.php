<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityReviewRequest;
use App\Models\Activity;
use App\Models\ActivityReview;
use App\Models\Reservation;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ActivityReviewController extends Controller
{
    public function index(Activity $activity): JsonResponse
    {
        abort_unless($activity->status === ActivityStatus::Published, 404);
        $reviews = ActivityReview::query()->where('activity_id', $activity->id)->where('status', 'approved')->with('user:id,name')->latest('reviewed_at')->paginate(20);
        $reviews->through(fn (ActivityReview $review) => [...$review->toArray(), 'customer_name' => $review->user->name]);

        return response()->json($reviews);
    }

    public function store(StoreActivityReviewRequest $request, Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        $reservation = Reservation::query()->where('ulid', $request->validated('reservation_ulid'))->where('activity_id', $activity->id)->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless(in_array($reservation->status, [ReservationStatus::Completed, ReservationStatus::Confirmed], true) && ($reservation->status === ReservationStatus::Completed || $reservation->starts_at->isPast()), 403, 'Serve una prenotazione realmente usufruita.');
        $review = ActivityReview::query()->create([...$request->safe()->except('reservation_ulid'), 'ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'reservation_id' => $reservation->id, 'user_id' => $request->user()->id, 'status' => 'approved', 'reviewed_at' => now()]);
        $analytics->recordActivity('activity_review_created', $activity, $request->user(), $reservation, "activity_review_created:{$review->id}");

        return response()->json(['data' => $review], 201);
    }
}
