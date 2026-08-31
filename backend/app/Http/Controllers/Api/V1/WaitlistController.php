<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Waitlist\AcceptWaitlistOffer;
use App\Actions\Waitlist\JoinEventWaitlist;
use App\Actions\Waitlist\LeaveEventWaitlist;
use App\Actions\Waitlist\PromoteEventWaitlist;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptWaitlistOfferRequest;
use App\Http\Requests\JoinWaitlistRequest;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Event;
use App\Models\WaitlistEntry;
use Illuminate\Http\JsonResponse;

class WaitlistController extends Controller
{
    public function show(Event $event): JsonResponse
    {
        $entry = WaitlistEntry::query()->where('event_id', $event->id)->where('user_id', request()->user()->id)
            ->with(['event', 'booking'])->first();

        return response()->json(['data' => $entry ? WaitlistEntryResource::make($entry) : null]);
    }

    public function store(JoinWaitlistRequest $request, Event $event, JoinEventWaitlist $join): JsonResponse
    {
        $entry = $join->handle($request->user(), $event, $request->validated());

        return response()->json(['data' => WaitlistEntryResource::make($entry)], 201);
    }

    public function destroy(Event $event, LeaveEventWaitlist $leave, PromoteEventWaitlist $promote): JsonResponse
    {
        $entry = $leave->handle(request()->user(), $event);
        $promote->handle($event->id);

        return response()->json(['data' => $entry ? WaitlistEntryResource::make($entry) : null]);
    }

    public function accept(AcceptWaitlistOfferRequest $request, Event $event, AcceptWaitlistOffer $accept): JsonResponse
    {
        $booking = $accept->handle($request->user(), $event, $request->validated());

        return response()->json(['data' => $booking], 201);
    }
}
