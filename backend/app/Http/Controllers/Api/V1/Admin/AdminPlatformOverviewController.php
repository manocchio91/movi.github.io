<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\Province;
use App\Models\Review;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class AdminPlatformOverviewController extends Controller
{
    public function show(): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $events = Event::query()
            ->with(['organizerProfile.owner', 'categories', 'municipality.province', 'reviewStatistic'])
            ->withCount([
                'bookings',
                'reviews',
                'bookings as participants_count' => fn ($query) => $query->whereIn('status', ['checked_in', 'completed']),
                'bookings as customer_cancellations_count' => fn ($query) => $query->where('status', 'cancelled_by_customer'),
            ])
            ->latest('starts_at')
            ->limit(80)
            ->get();

        $organizers = OrganizerProfile::query()
            ->with(['owner', 'municipality.province', 'reviewStatistic'])
            ->withCount(['events', 'followers'])
            ->latest()
            ->limit(80)
            ->get();

        $customers = User::query()
            ->where('role', UserRole::Customer->value)
            ->withCount(['bookings', 'reviews', 'favorites', 'followedOrganizers'])
            ->latest()
            ->limit(80)
            ->get();

        $reviews = Review::query()
            ->with(['user:id,name,email', 'event:id,ulid,title,organizer_profile_id', 'organizerProfile:id,ulid,name'])
            ->latest('reviewed_at')
            ->limit(80)
            ->get();

        return response()->json([
            'data' => [
                'kpis' => $this->kpis(),
                'charts' => $this->charts(),
                'latest_events' => $this->eventRows($events->take(8)),
                'latest_registrations' => [
                    'organizers' => $this->organizerRows($organizers->take(6)),
                    'customers' => $this->customerRows($customers->take(6)),
                ],
                'latest_reviews' => $this->reviewRows($reviews->take(8)),
                'events' => $this->eventRows($events),
                'organizers' => $this->organizerRows($organizers),
                'customers' => $this->customerRows($customers),
                'reviews' => $this->reviewRows($reviews),
                'categories' => $this->categories(),
                'territory' => $this->territory(),
                'subscriptions' => $this->subscriptions(),
                'audit_logs' => $this->auditLogs(),
            ],
        ]);
    }

    private function kpis(): array
    {
        return [
            'organizers_registered' => OrganizerProfile::query()->count(),
            'customers_registered' => User::query()->where('role', UserRole::Customer->value)->count(),
            'events_published' => Event::query()->where('status', EventStatus::Published->value)->count(),
            'events_scheduled' => Event::query()->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])->where('starts_at', '>=', now())->count(),
            'events_completed' => Event::query()->where('status', EventStatus::Completed->value)->count(),
            'events_cancelled' => Event::query()->where('status', EventStatus::Cancelled->value)->count(),
            'bookings_total' => Booking::query()->count(),
            'participants_total' => (int) Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_people'),
            'events_created_today' => Event::query()->whereDate('created_at', today())->count(),
            'bookings_today' => Booking::query()->whereDate('booked_at', today())->count(),
        ];
    }

    private function charts(): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->format('Y-m'));

        return [
            'events_by_month' => $this->monthly(Event::query()->get(['created_at']), $months),
            'bookings_by_month' => $this->monthly(Booking::query()->get(['booked_at']), $months, 'booked_at'),
            'organizer_growth' => $this->monthly(OrganizerProfile::query()->get(['created_at']), $months),
            'customer_growth' => $this->monthly(User::query()->where('role', UserRole::Customer->value)->get(['created_at']), $months),
            'events_by_category' => Category::query()
                ->withCount('events')
                ->orderByDesc('events_count')
                ->limit(8)
                ->get()
                ->map(fn (Category $category) => ['label' => $category->name, 'value' => $category->events_count])
                ->values(),
        ];
    }

    private function monthly(Collection $items, Collection $months, string $field = 'created_at'): array
    {
        return $months->map(fn (string $month) => [
            'label' => $month,
            'value' => $items->filter(fn ($item) => $item->{$field} && Carbon::parse($item->{$field})->format('Y-m') === $month)->count(),
        ])->values()->all();
    }

    private function eventRows(Collection $events): array
    {
        return $events->map(fn (Event $event) => [
            'ulid' => $event->ulid,
            'title' => $event->title,
            'organizer' => $event->organizerProfile?->name,
            'category' => $event->categories->first()?->name ?? 'Senza categoria',
            'municipality' => $event->municipality?->name,
            'province' => $event->municipality?->province?->code,
            'date' => $event->starts_at?->toDateString(),
            'status' => $event->status->value,
            'booked' => $event->bookings_count,
            'participants' => $event->participants_count,
            'customer_cancellations' => $event->customer_cancellations_count,
            'average_rating' => (float) ($event->reviewStatistic?->average_rating ?? 0),
            'fill_rate' => $event->capacity_total > 0 ? round((($event->capacity_total - $event->available_total) / $event->capacity_total) * 100, 1) : 0,
            'success_score' => $event->successScore?->success_score ?? null,
        ])->values()->all();
    }

    private function organizerRows(Collection $organizers): array
    {
        return $organizers->map(function (OrganizerProfile $organizer): array {
            $eventQuery = $organizer->events();

            return [
                'ulid' => $organizer->ulid,
                'logo' => $organizer->logo_path,
                'name' => $organizer->name,
                'municipality' => $organizer->municipality?->name,
                'province' => $organizer->municipality?->province?->code,
                'events_created' => $organizer->events_count,
                'future_events' => (clone $eventQuery)->where('starts_at', '>=', now())->count(),
                'completed_events' => (clone $eventQuery)->where('status', EventStatus::Completed->value)->count(),
                'cancelled_events' => (clone $eventQuery)->where('status', EventStatus::Cancelled->value)->count(),
                'bookings_received' => Booking::query()->whereIn('event_id', (clone $eventQuery)->pluck('id'))->count(),
                'average_rating' => (float) ($organizer->reviewStatistic?->average_rating ?? 0),
                'followers' => $organizer->followers_count,
            ];
        })->values()->all();
    }

    private function customerRows(Collection $customers): array
    {
        return $customers->map(fn (User $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'municipality' => $customer->municipality ?? null,
            'events_attended' => $customer->bookings()->whereIn('status', ['checked_in', 'completed'])->count(),
            'reviews_left' => $customer->reviews_count,
            'cancelled_events' => $customer->bookings()->whereIn('status', ['cancelled_by_customer', 'cancelled_by_organizer'])->count(),
            'favorites' => $customer->favorites_count,
            'followed_organizers' => $customer->followed_organizers_count,
            'registered_at' => $customer->created_at?->toDateString(),
        ])->values()->all();
    }

    private function reviewRows(Collection $reviews): array
    {
        return $reviews->map(fn (Review $review) => [
            'ulid' => $review->ulid,
            'user' => $review->user?->name,
            'event' => $review->event?->title,
            'organizer' => $review->organizerProfile?->name,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'status' => $review->hidden_at ? 'hidden' : $review->status,
            'would_recommend' => $review->would_recommend,
            'reviewed_at' => $review->reviewed_at?->toDateString(),
        ])->values()->all();
    }

    private function categories(): array
    {
        return Category::query()
            ->withCount('events')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => 'Tag',
                'color' => '#0f766e',
                'events_count' => $category->events_count,
                'organizers_count' => Event::query()->whereHas('categories', fn ($query) => $query->whereKey($category->id))->distinct('organizer_profile_id')->count('organizer_profile_id'),
            ])->values()->all();
    }

    private function territory(): array
    {
        return [
            'regions' => Province::query()
                ->with('region')
                ->get()
                ->groupBy('region.name')
                ->map(fn (Collection $provinces, string $name) => [
                    'name' => $name,
                    'provinces_count' => $provinces->count(),
                    'events_count' => Municipality::query()->whereIn('province_id', $provinces->pluck('id'))->withCount('events')->get()->sum('events_count'),
                ])->values()->all(),
            'municipalities' => Municipality::query()
                ->with('province')
                ->withCount('events')
                ->orderByDesc('events_count')
                ->limit(20)
                ->get()
                ->map(fn (Municipality $municipality) => [
                    'name' => $municipality->name,
                    'province' => $municipality->province?->code,
                    'events_count' => $municipality->events_count,
                ])->values()->all(),
        ];
    }

    private function subscriptions(): array
    {
        return OrganizerSubscription::query()
            ->with(['plan', 'organizerProfile'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OrganizerSubscription $subscription) => [
                'organizer' => $subscription->organizerProfile?->name,
                'plan' => $subscription->plan?->name ?? SubscriptionPlan::query()->find($subscription->subscription_plan_id)?->name,
                'renewal' => $subscription->current_period_ends_at?->toDateString(),
                'status' => $subscription->status,
                'payments' => 'predisposto',
            ])->values()->all();
    }

    private function auditLogs(): array
    {
        return AuditLog::query()
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'subject_type' => class_basename($log->subject_type),
                'subject_id' => $log->subject_id,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])->values()->all();
    }
}
