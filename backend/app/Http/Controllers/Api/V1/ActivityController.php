<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'type' => ['nullable', Rule::enum(ActivityType::class)],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $activities = Activity::query()->where('status', ActivityStatus::Published)->where('booking_enabled', true)
            ->whereHas('organizerProfile', fn ($query) => $query->where('verification_status', 'verified')->whereNull('suspended_at')->whereHas('owner', fn ($owner) => $owner->where('status', 'active')))
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->when(isset($filters['municipality_id']), fn ($query) => $query->where('municipality_id', $filters['municipality_id']))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->with(['municipality', 'organizerProfile', 'services' => fn ($q) => $q->where('active', true)->where('booking_enabled', true)])
            ->withCount(['followers', 'reviews' => fn ($q) => $q->where('status', 'approved')])->withAvg(['reviews' => fn ($q) => $q->where('status', 'approved')], 'rating')->orderBy('name')->paginate(15);

        $activities->getCollection()->each(fn (Activity $activity) => $activity->setAttribute('next_available_at', $this->nextAvailableAt($activity, $availability)));

        return ActivityResource::collection($activities)->response();
    }

    public function show(Request $request, Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless($this->isPublic($activity), 404);
        $analytics->recordActivityDetail($activity, $request->user(), $request->header('X-Analytics-Session'));
        $user = $request->user();
        $activity->load(['municipality', 'organizerProfile', 'services' => fn ($q) => $q->where('active', true)->where('booking_enabled', true), 'events' => fn ($q) => $q->whereIn('status', [EventStatus::Published, EventStatus::SoldOut])->where('starts_at', '>=', now())->where('municipality_id', $activity->municipality_id)->with(['categories', 'municipality', 'ticketTypes'])->orderBy('starts_at')->limit(6), 'followers' => fn ($q) => $user ? $q->whereKey($user->id) : $q->whereRaw('1 = 0')])->loadCount(['followers', 'reviews' => fn ($q) => $q->where('status', 'approved')])->loadAvg(['reviews' => fn ($q) => $q->where('status', 'approved')], 'rating');
        $activity->setAttribute('next_available_at', $this->nextAvailableAt($activity, app(AvailabilityService::class)));

        return response()->json(['data' => ActivityResource::make($activity)]);
    }

    public function availability(Request $request, Activity $activity, ActivityService $service, AvailabilityService $availability): JsonResponse
    {
        abort_unless($this->isPublic($activity) && $activity->booking_enabled && $service->activity_id === $activity->id && $service->active && $service->booking_enabled, 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $availability->forDate($service, CarbonImmutable::parse($data['date']))]);
    }

    private function isPublic(Activity $activity): bool
    {
        return $activity->status === ActivityStatus::Published
            && $activity->organizerProfile()->where('verification_status', 'verified')->whereNull('suspended_at')->whereHas('owner', fn ($owner) => $owner->where('status', 'active'))->exists();
    }

    private function nextAvailableAt(Activity $activity, AvailabilityService $availability): ?string
    {
        foreach (range(0, 30) as $offset) {
            $date = CarbonImmutable::today()->addDays($offset);
            foreach ($activity->services as $service) {
                $slot = collect($availability->forDate($service, $date))->first(fn (array $candidate): bool => $candidate['available'] > 0 && CarbonImmutable::parse($candidate['starts_at'])->isFuture());
                if ($slot !== null) {
                    return $slot['starts_at'];
                }
            }
        }

        return null;
    }
}
