<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CheckInBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInBookingRequest;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class OrganizerCheckInController extends Controller
{
    public function store(
        CheckInBookingRequest $request,
        Event $event,
        CheckInBooking $checkIn,
    ): JsonResponse {
        $this->authorize('viewOrganizer', $event);

        $result = $checkIn->handle(
            $request->user(),
            $event,
            $request->validated('qr_token'),
        );

        return response()->json([
            'data' => [
                'booking_ulid' => $result->booking->ulid,
                'booking_status' => $result->booking->status,
                'checked_in_at' => $result->checked_in_at,
            ],
        ], 201);
    }
}
