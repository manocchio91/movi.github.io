<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;

class OrganizerEventSummaryService
{
    public function summarize(Event $event): array
    {
        $bookings = $event->bookings()->whereIn('status', ['confirmed', 'checked_in', 'completed']);
        $bookedPeople = (int) (clone $bookings)->sum('total_people');
        $checkedInPeople = (int) $event->bookings()
            ->where(function ($query): void {
                $query->whereIn('status', ['checked_in', 'completed'])
                    ->orWhereExists(function ($subquery): void {
                        $subquery->selectRaw('1')
                            ->from('check_ins')
                            ->whereColumn('check_ins.booking_id', 'bookings.id');
                    });
            })
            ->sum('total_people');
        $noShow = max(0, $bookedPeople - $checkedInPeople);
        $fillRate = $event->capacity_total > 0 ? round(($bookedPeople / $event->capacity_total) * 100, 2) : 0;
        $attendanceRate = $bookedPeople > 0 ? round(($checkedInPeople / $bookedPeople) * 100, 2) : 0;
        $reviewStats = $event->reviewStatistic;
        $visualStatus = $this->visualStatus($event);
        $globalAvailable = (int) $event->getAttribute('available_total');
        $activeTickets = $event->ticketTypes->where('is_active', true);
        $ticketAvailable = $activeTickets->isEmpty()
            ? $globalAvailable
            : (int) $activeTickets->sum(fn ($ticket) => $ticket->getAttribute('max_quantity') === null
                ? $globalAvailable
                : max(0, (int) $ticket->getAttribute('max_quantity') - (int) $ticket->getAttribute('booked_quantity')));
        $bookableAvailable = min($globalAvailable, $ticketAvailable);

        return [
            'ulid' => $event->ulid,
            'title' => $event->title,
            'visual_status' => $visualStatus,
            'status' => $event->status->value,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'booking_closes_at' => $event->booking_closes_at,
            'municipality' => $event->municipality?->name,
            'categories' => $event->categories->pluck('name')->values(),
            'price_from_cents' => (int) $event->ticketTypes->min('price_cents'),
            'capacity_total' => $event->capacity_total,
            'booked_people' => $bookedPeople,
            'available_total' => $bookableAvailable,
            'capacity_available_total' => $globalAvailable,
            'fill_rate' => $fillRate,
            'checked_in_people' => $checkedInPeople,
            'no_show' => $noShow,
            'attendance_rate' => $attendanceRate,
            'average_rating' => (float) ($reviewStats?->average_rating ?: 0),
            'reviews_count' => (int) ($reviewStats?->reviews_count ?: 0),
            'bookings_open' => $visualStatus === 'Aperto',
        ];
    }

    public function visualStatus(Event $event): string
    {
        if ($event->status === EventStatus::Cancelled) {
            return 'Annullato';
        }

        if ($event->status === EventStatus::SoldOut) {
            return 'Sold out';
        }

        if ($event->status === EventStatus::Draft) {
            return 'Bozza';
        }

        if ($event->status === EventStatus::PendingReview) {
            return 'In attesa di approvazione';
        }

        if (
            $event->status === EventStatus::Completed
            || ($event->ends_at ?? $event->starts_at)->isPast()
            || ($event->booking_closes_at && $event->booking_closes_at->isPast())
        ) {
            return 'Chiuso';
        }

        if ($event->status === EventStatus::Published && $event->starts_at->isFuture()) {
            return 'Aperto';
        }

        return ucfirst(str_replace('_', ' ', $event->status->value));
    }
}
