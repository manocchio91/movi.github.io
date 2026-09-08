<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\ResolveBookingQr;
use App\Actions\Bookings\UpdateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Review;
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

        $booking->makeVisible('contact_phone');

        return response()->json(['data' => $booking], 201);
    }

    public function index(): JsonResponse
    {
        $bookings = request()->user()->bookings()->with(['event.categories', 'items.ticketType'])->latest()->get();
        $bookings->each(function (Booking $booking): void {
            $booking->makeVisible('contact_phone');
            $booking->setAttribute('can_cancel', $booking->status === 'confirmed' && $booking->event->starts_at->isFuture());
            $booking->setAttribute('can_edit', $booking->status === 'confirmed' && $booking->event->starts_at->isFuture());
            $concludedAt = $booking->event->ends_at ?? $booking->event->starts_at;
            $booking->setAttribute('can_review', in_array($booking->status, ['confirmed', 'checked_in', 'completed'], true)
                && $concludedAt->isPast()
                && ! Review::query()->where('booking_id', $booking->id)->exists());
        });

        return response()->json([
            'data' => $bookings,
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        return response()->json(['data' => $booking->load(['event.categories', 'items.ticketType'])->makeVisible('contact_phone')]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking, UpdateBooking $updateBooking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $booking = $updateBooking->handle($request->user(), $booking, $request->validated());
        $booking->makeVisible('contact_phone');
        $booking->setAttribute('can_cancel', true);
        $booking->setAttribute('can_edit', true);

        return response()->json(['data' => $booking]);
    }

    public function cancel(Booking $booking, CancelBooking $cancelBooking, OperationalEmailService $emails): JsonResponse
    {
        abort_unless($booking->user_id === request()->user()->id, 403);

        $booking = $cancelBooking->handle($booking)->load(['user', 'event']);
        $emails->bookingCancelled($booking);

        $booking->makeVisible('contact_phone');

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
