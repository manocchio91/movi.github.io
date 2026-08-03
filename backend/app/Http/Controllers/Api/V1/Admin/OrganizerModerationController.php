<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Organizers\ReviewOrganizerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAdminOrganizerRequest;
use App\Http\Requests\ReviewOrganizerRequest;
use App\Models\OrganizerProfile;
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

        return response()->json([
            'data' => $query->paginate((int) ($request->validated('per_page') ?? 15)),
            'pending_count' => OrganizerProfile::query()->where('verification_status', 'pending')->count(),
        ]);
    }

    public function show(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json(['data' => $organizer->load(['owner', 'municipality'])]);
    }

    public function approve(OrganizerProfile $organizer, ReviewOrganizerProfile $review): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json(['data' => $review->execute($organizer, request()->user(), 'approve')]);
    }

    public function requestChanges(ReviewOrganizerRequest $request, OrganizerProfile $organizer, ReviewOrganizerProfile $review): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json(['data' => $review->execute($organizer, $request->user(), 'request_changes', $request->validated('reason'))]);
    }

    public function reject(ReviewOrganizerRequest $request, OrganizerProfile $organizer, ReviewOrganizerProfile $review): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json(['data' => $review->execute($organizer, $request->user(), 'reject', $request->validated('reason'))]);
    }
}
