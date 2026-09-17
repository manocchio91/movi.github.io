<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityReviewRequest;
use App\Models\Activity;
use App\Models\ActivityReview;
use App\Models\Booking;
use App\Models\Reservation;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Reviews\ReviewEligibility;
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

    public function store(StoreActivityReviewRequest $request, Activity $activity, AnalyticsRecorder $analytics, ReviewEligibility $eligibility): JsonResponse
    {
        $reservation = null;
        $booking = null;
        if ($request->validated('reservation_ulid')) {
            $reservation = Reservation::query()->where('ulid', $request->validated('reservation_ulid'))->where('activity_id', $activity->id)->where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($eligibility->activityReservation($reservation), 403, 'La recensione è disponibile dal giorno successivo alla visita.');
        } else {
            $booking = Booking::query()->where('ulid', $request->validated('booking_ulid'))->where('user_id', $request->user()->id)
                ->whereIn('status', ['confirmed', 'checked_in', 'completed'])->whereHas('event', fn ($query) => $query->where('organizer_profile_id', $activity->organizer_profile_id)->where('municipality_id', $activity->municipality_id))->with('event')->firstOrFail();
            $concludedAt = $booking->event->ends_at ?? $booking->event->starts_at;
            abort_unless(now()->startOfDay()->gte($concludedAt->copy()->startOfDay()->addDay()), 403, 'La recensione è disponibile dal giorno successivo alla visita.');
        }
        $review = ActivityReview::query()->create([...$request->safe()->except(['reservation_ulid', 'booking_ulid']), 'ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'reservation_id' => $reservation?->id, 'booking_id' => $booking?->id, 'user_id' => $request->user()->id, 'status' => 'approved', 'reviewed_at' => now()]);
        $analytics->recordActivity('activity_review_created', $activity, $request->user(), $reservation, "activity_review_created:{$review->id}");

        return response()->json(['data' => $review], 201);
    }
}
