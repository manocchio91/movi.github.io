<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\CreateReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Event;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\ReviewStatistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        return response()->json([
            'statistics' => ReviewStatistic::query()->where('event_id', $event->id)->first(),
            'data' => $event->reviews()
                ->with(['user:id,name', 'images'])
                ->where('status', 'approved')
                ->whereNull('hidden_at')
                ->latest('reviewed_at')
                ->paginate(10),
        ]);
    }

    public function store(StoreReviewRequest $request, Event $event, CreateReview $createReview): JsonResponse
    {
        $review = $createReview->handle($request->user(), $event, $request->validated());

        return response()->json(['data' => $review], 201);
    }

    public function report(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ReviewReport::query()->updateOrCreate(
            ['review_id' => $review->id, 'user_id' => $request->user()?->id],
            [...$data, 'status' => 'open'],
        );

        return response()->json(['data' => $report], 201);
    }
}
