<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizerBookingService
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->visibleQuery($actor)->with(['user:id,name,email', 'event:id,ulid,title,organizer_profile_id,starts_at', 'items.ticketType:id,name', 'checkIns']);
        $search = trim((string) ($filters['search'] ?? ''));
        $filter = (string) ($filters['filter'] ?? 'all');

        $query->when($search !== '', fn (Builder $bookings) => $bookings->where(function (Builder $nested) use ($search): void {
            $nested->where('booking_code', 'ilike', "%{$search}%")
                ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                ->orWhereHas('event', fn (Builder $events) => $events->where('title', 'ilike', "%{$search}%"));
        }));

        $query->when($filter === 'confirmed', fn (Builder $q) => $q->where('status', 'confirmed'))
            ->when($filter === 'checked_in', fn (Builder $q) => $q->where(function (Builder $nested): void {
                $nested->where('status', 'checked_in')->orWhereHas('checkIns');
            }))
            ->when($filter === 'completed', fn (Builder $q) => $q->where('status', 'completed'))
            ->when($filter === 'cancelled', fn (Builder $q) => $q->whereIn('status', ['cancelled', 'cancelled_by_customer', 'cancelled_by_organizer']));

        $sort = (string) ($filters['sort'] ?? 'desc');
        $sortBy = (string) ($filters['sort_by'] ?? 'booked_at');
        match ($sortBy) {
            'event' => $query->orderBy((new Event)->select('title')->whereColumn('events.id', 'bookings.event_id'), $sort),
            'customer' => $query->orderBy((new User)->select('name')->whereColumn('users.id', 'bookings.user_id'), $sort),
            'people' => $query->orderBy('total_people', $sort),
            'status' => $query->orderBy('status', $sort),
            default => $query->orderBy('booked_at', $sort),
        };

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function findVisible(User $actor, Booking $booking): Booking
    {
        abort_unless($this->visibleQuery($actor)->whereKey($booking->id)->exists(), 403);

        return $booking->load(['user:id,name,email', 'event:id,ulid,title,organizer_profile_id,starts_at', 'items.ticketType:id,name', 'checkIns']);
    }

    /** @return Builder<Booking> */
    private function visibleQuery(User $actor): Builder
    {
        if ($actor->role === UserRole::Admin) {
            return Booking::query();
        }

        $profile = $actor->organizerProfile;
        abort_unless($profile?->verification_status === 'verified', 403);

        return Booking::query()->whereHas('event', fn (Builder $events) => $events->where('organizer_profile_id', $profile->id));
    }
}
