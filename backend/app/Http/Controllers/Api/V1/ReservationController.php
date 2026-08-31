<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\CreateReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(): JsonResponse
    {
        return ReservationResource::collection(request()->user()->reservations()->with(['activity.municipality', 'service'])->latest('starts_at')->paginate(50))->response();
    }

    public function store(StoreReservationRequest $request, Activity $activity, CreateReservation $create): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || mb_strlen($key) > 120, 422, 'Idempotency-Key obbligatoria.');
        $service = ActivityService::query()->where('ulid', $request->validated('service_ulid'))->firstOrFail();

        return response()->json(['data' => ReservationResource::make($create->handle($request->user(), $activity, $service, $request->validated(), $key))], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        return response()->json(['data' => ReservationResource::make($reservation->load(['activity.municipality', 'service', 'user']))]);
    }

    public function cancel(Request $request, Reservation $reservation, CancelReservation $cancel): JsonResponse
    {
        $this->authorize('cancel', $reservation);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => ReservationResource::make($cancel->handle($request->user(), $reservation, $data['reason'] ?? null))]);
    }
}
