<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, Event $event, CreateBooking $createBooking): JsonResponse
    {
        $booking = $createBooking->handle(
            $request->user(),
            $event,
            $request->validated(),
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key'),
        );

        return response()->json(['data' => $booking], 201);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => request()->user()->bookings()->with(['event.categories', 'items.ticketType'])->latest()->get(),
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        return response()->json(['data' => $booking->load(['event.categories', 'items.ticketType'])]);
    }

    public function cancel(Booking $booking, CancelBooking $cancelBooking): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        return response()->json(['data' => $cancelBooking->handle($booking)]);
    }
}
