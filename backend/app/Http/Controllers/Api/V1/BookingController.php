<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\ResolveBookingQr;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Event;
use App\Services\OperationalEmailService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, Event $event, CreateBooking $createBooking, OperationalEmailService $emails): JsonResponse
    {
        $booking = $createBooking->handle(
            $request->user(),
            $event,
            $request->validated(),
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key'),
        );
        $emails->bookingConfirmed($booking);

        return response()->json(['data' => $booking], 201);
    }

    public function index(): JsonResponse
    {
        $bookings = request()->user()->bookings()->with(['event.categories', 'items.ticketType'])->latest()->get();
        $bookings->each(fn (Booking $booking) => $booking->setAttribute(
            'can_cancel',
            $booking->status === 'confirmed' && $booking->event->starts_at->isFuture(),
        ));

        return response()->json([
            'data' => $bookings,
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        return response()->json(['data' => $booking->load(['event.categories', 'items.ticketType'])]);
    }

    public function cancel(Booking $booking, CancelBooking $cancelBooking, OperationalEmailService $emails): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        $booking = $cancelBooking->handle($booking)->load(['user', 'event']);
        $emails->bookingCancelled($booking);

        return response()->json(['data' => $booking]);
    }

    public function qr(Booking $booking, ResolveBookingQr $resolveQr): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);
        $token = $resolveQr->handle($booking);
        abort_if($token === null, 409, 'QR non disponibile per questa prenotazione.');

        return response()->json([
            'data' => [
                'booking_ulid' => $booking->ulid,
                'qr_token' => $token,
            ],
        ]);
    }
}
