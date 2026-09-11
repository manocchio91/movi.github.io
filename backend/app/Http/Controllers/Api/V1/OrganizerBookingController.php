<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexOrganizerBookingRequest;
use App\Models\Booking;
use App\Services\OrganizerBookingService;
use Illuminate\Http\JsonResponse;

class OrganizerBookingController extends Controller
{
    public function index(IndexOrganizerBookingRequest $request, OrganizerBookingService $service): JsonResponse
    {
        $bookings = $service->paginate($request->user(), $request->validated());

        return response()->json([
            'data' => $bookings->getCollection()->map(fn (Booking $booking) => $this->serialize($booking, false))->values(),
            'meta' => ['current_page' => $bookings->currentPage(), 'last_page' => $bookings->lastPage(), 'per_page' => $bookings->perPage(), 'total' => $bookings->total(), 'from' => $bookings->firstItem(), 'to' => $bookings->lastItem()],
        ]);
    }

    public function show(Booking $booking, OrganizerBookingService $service): JsonResponse
    {
        return response()->json(['data' => $this->serialize($service->findVisible(request()->user(), $booking), true)]);
    }

    /** @return array<string, mixed> */
    private function serialize(Booking $booking, bool $detail): array
    {
        $data = [
            'ulid' => $booking->ulid, 'booking_code' => $booking->booking_code,
            'customer' => ['name' => $booking->user->name, 'email' => $booking->user->email, 'phone' => $booking->contact_phone],
            'event' => ['ulid' => $booking->event->ulid, 'title' => $booking->event->title, 'starts_at' => $booking->event->starts_at],
            'people' => $booking->total_people, 'status' => $booking->status, 'booked_at' => $booking->booked_at,
            'checked_in_at' => $booking->checkIns->first()?->checked_in_at,
        ];

        return $detail ? $data + [
            'tickets' => $booking->items->map(fn ($item) => ['name' => $item->ticketType->name, 'quantity' => $item->quantity])->values(),
            'qr_token' => $booking->qr_token_encrypted,
            'notes' => $booking->notes,
        ] : $data;
    }
}
