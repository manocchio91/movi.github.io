<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Events\ModerateEvent;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class EventModerationController extends Controller
{
    public function pending(): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json([
            'data' => Event::query()->where('status', EventStatus::PendingReview)->with(['organizerProfile', 'categories'])->get(),
        ]);
    }

    public function approve(Event $event, ModerateEvent $moderateEvent): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $moderateEvent->approve($event, request()->user())]);
    }

    public function reject(Event $event, ModerateEvent $moderateEvent): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $moderateEvent->reject($event, request()->user())]);
    }

    public function suspend(Event $event, ModerateEvent $moderateEvent): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $moderateEvent->suspend($event, request()->user())]);
    }
}
