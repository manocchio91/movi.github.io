<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventAnalyticsController extends Controller
{
    public function share(Request $request, Event $event, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless(in_array($event->status, [EventStatus::Published, EventStatus::SoldOut], true), 404);
        $analytics->record('event_share', $event, $request->user());

        return response()->json(['data' => ['recorded' => true]]);
    }
}
