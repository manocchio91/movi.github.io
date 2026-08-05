<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Organizers\ReviewOrganizerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAdminOrganizerRequest;
use App\Http\Requests\ReviewOrganizerRequest;
use App\Models\OrganizerProfile;
use App\Services\OperationalEmailService;
use Illuminate\Http\JsonResponse;

class OrganizerModerationController extends Controller
{
    public function index(IndexAdminOrganizerRequest $request): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);
        $query = OrganizerProfile::query()->with(['owner', 'municipality'])->latest('submitted_at')->latest('id');

        if ($status = $request->validated('status')) {
            $query->where('verification_status', $status);
        }
        if ($commercialStatus = $request->validated('commercial_status')) {
            $query->whereHas('subscription', fn ($builder) => $builder->where('status', $commercialStatus));
        }
        if ($plan = $request->validated('plan')) {
            $query->whereHas('subscription.plan', fn ($builder) => $builder->where('billing_model', $plan));
        }
        if ($search = $request->validated('q')) {
            $query->where(fn ($builder) => $builder->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')->orWhereHas('owner', fn ($owner) => $owner->where('email', 'ilike', '%'.$search.'%')));
        }

        return response()->json([
            'data' => $query->paginate((int) ($request->validated('per_page') ?? 15)),
            'pending_count' => OrganizerProfile::query()->where('verification_status', 'pending')->count(),
        ]);
    }

    public function show(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json(['data' => $organizer->load(['owner', 'municipality', 'subscription.plan'])]);
    }

    public function approve(OrganizerProfile $organizer, ReviewOrganizerProfile $review, OperationalEmailService $emails): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        $organizer = $review->execute($organizer, request()->user(), 'approve')->load('owner');
        $emails->organizerReviewed($organizer);

        return response()->json(['data' => $organizer]);
    }

    public function requestChanges(ReviewOrganizerRequest $request, OrganizerProfile $organizer, ReviewOrganizerProfile $review, OperationalEmailService $emails): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        $organizer = $review->execute($organizer, $request->user(), 'request_changes', $request->validated('reason'))->load('owner');
        $emails->organizerReviewed($organizer);

        return response()->json(['data' => $organizer]);
    }

    public function reject(ReviewOrganizerRequest $request, OrganizerProfile $organizer, ReviewOrganizerProfile $review, OperationalEmailService $emails): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        $organizer = $review->execute($organizer, $request->user(), 'reject', $request->validated('reason'))->load('owner');
        $emails->organizerReviewed($organizer);

        return response()->json(['data' => $organizer]);
    }

    public function suspend(ReviewOrganizerRequest $request, OrganizerProfile $organizer): JsonResponse
    {
        abort_unless($organizer->verification_status === 'verified', 409, 'Solo un Organizer approvato può essere sospeso.');
        $organizer->forceFill(['verification_status' => 'suspended', 'suspended_at' => now(), 'review_notes' => $request->validated('reason')])->save();

        return response()->json(['data' => $organizer->refresh()->load(['owner', 'subscription.plan'])]);
    }

    public function reactivate(OrganizerProfile $organizer): JsonResponse
    {
        abort_unless($organizer->verification_status === 'suspended', 409, 'Solo un Organizer sospeso può essere riattivato.');
        $organizer->forceFill(['verification_status' => 'verified', 'suspended_at' => null, 'review_notes' => null])->save();

        return response()->json(['data' => $organizer->refresh()->load(['owner', 'subscription.plan'])]);
    }
}
