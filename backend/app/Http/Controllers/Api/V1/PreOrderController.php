<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PreOrders\UpsertPreOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertPreOrderRequest;
use App\Http\Resources\PreOrderResource;
use App\Models\PreOrder;
use App\Models\Reservation;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;

class PreOrderController extends Controller
{
    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        return response()->json(['data' => PreOrderResource::make($reservation->preorder()->with('items.menuItem')->firstOrFail()), 'deadline' => $reservation->starts_at->copy()->subMinutes($reservation->service->preorder_edit_deadline_minutes)]);
    }

    public function store(UpsertPreOrderRequest $request, Reservation $reservation, UpsertPreOrder $action): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        return response()->json(['data' => PreOrderResource::make($action->handle($reservation, $request->validated()))]);
    }

    public function cancel(Reservation $reservation, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless($reservation->user_id === request()->user()->id, 403);
        /** @var PreOrder $preorder */
        $preorder = $reservation->preorder()->where('status', 'confirmed')->firstOrFail();
        abort_if(now()->greaterThanOrEqualTo($preorder->getAttribute('locked_at')), 422, 'La deadline del preordine è trascorsa.');
        $preorder->update(['status' => 'cancelled']);
        $analytics->recordActivity('preorder_cancelled', $reservation->activity, $reservation->user, $reservation);

        return response()->json(['data' => PreOrderResource::make($preorder->load('items.menuItem'))]);
    }
}
