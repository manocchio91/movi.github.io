<?php

namespace App\Services\Analytics;

use App\Enums\ReservationStatus;
use App\Models\OrganizerProfile;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class OrganizerReservationAnalytics
{
    public function calculate(OrganizerProfile $profile, array $filters): array
    {
        [$from, $to, $label] = $this->period($filters);
        $owned = Reservation::query()->whereHas('activity', fn (Builder $query) => $query->where('organizer_profile_id', $profile->id));
        $period = (clone $owned)->whereBetween('starts_at', [$from, $to]);
        $total = (clone $period)->count();
        $covers = (int) (clone $period)->sum('party_size');
        $cancelled = (clone $period)->where('status', ReservationStatus::Cancelled)->count();
        $completed = (clone $period)->where('status', ReservationStatus::Completed)->count();
        $noShow = (clone $period)->where('status', ReservationStatus::NoShow)->count();
        $validStatuses = [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value];
        $valid = (clone $period)->whereIn('status', $validStatuses);
        $customerIds = (clone $valid)->distinct()->pluck('user_id');
        $returnRows = (clone $owned)->whereIn('status', $validStatuses)->orderBy('starts_at')->get(['user_id', 'activity_id', 'starts_at']);
        $seen = [];
        $returningCustomers = [];
        foreach ($returnRows as $row) {
            $key = $row->user_id.':'.$row->activity_id;
            if ($row->starts_at->betweenIncluded($from, $to) && isset($seen[$key])) {
                $returningCustomers[$row->user_id] = true;
            }
            $seen[$key] = true;
        }
        $returning = count($returningCustomers);
        $unique = $customerIds->count();

        $preorders = DB::table('preorders')->join('reservations', 'reservations.id', '=', 'preorders.reservation_id')
            ->join('activities', 'activities.id', '=', 'reservations.activity_id')->where('activities.organizer_profile_id', $profile->id)
            ->where('preorders.status', 'confirmed')->whereBetween('reservations.starts_at', [$from, $to]);
        $preorderIds = (clone $preorders)->pluck('preorders.id');
        $preorderItems = DB::table('preorder_items')->whereIn('preorder_id', $preorderIds);
        $reviews = DB::table('activity_reviews')->whereIn('reservation_id', (clone $period)->select('id'))->where('status', 'approved');
        $averageRating = (clone $reviews)->avg('rating');

        $analytics = DB::table('analytics_events')->where('organizer_profile_id', $profile->id)->whereBetween('occurred_at', [$from, $to]);
        $views = (clone $analytics)->where('type', 'activity_detail_view')->count();
        $availabilityViews = (clone $analytics)->where('type', 'activity_availability_view')->count();
        $created = (clone $analytics)->where('type', 'reservation_created')->count();
        $reviewEvents = (clone $analytics)->where('type', 'activity_review_created')->count();

        $reservations = (clone $period)->get(['starts_at', 'party_size']);
        $days = $reservations->groupBy(fn (Reservation $reservation) => $reservation->starts_at->locale('it')->isoFormat('dddd'))
            ->map(fn ($rows, string $day) => ['label' => ucfirst($day), 'reservations' => $rows->count(), 'covers' => (int) $rows->sum('party_size')])->sortByDesc('reservations')->values()->all();
        $timeSlots = $reservations->groupBy(fn (Reservation $reservation) => $this->timeSlot($reservation->starts_at->hour))
            ->map(fn ($rows, string $slot) => ['label' => $slot, 'reservations' => $rows->count(), 'covers' => (int) $rows->sum('party_size')])->sortByDesc('reservations')->values()->all();

        return ['reservations' => $total, 'covers' => $covers, 'cancellations' => $cancelled, 'completed' => $completed, 'no_show' => $noShow,
            'cancellation_rate' => $total ? round($cancelled / $total * 100, 1) : 0, 'no_show_rate' => $total ? round($noShow / $total * 100, 1) : 0,
            'repeat_customers' => $returning, 'average_party_size' => $total ? round($covers / $total, 1) : 0,
            'period' => ['key' => $label, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'overview' => ['reservations' => $total, 'covers' => $covers, 'unique_customers' => $unique, 'cancellations' => $cancelled,
                'completed' => $completed, 'no_show' => $noShow, 'cancellation_rate' => $total ? round($cancelled / $total * 100, 1) : 0,
                'no_show_rate' => $total ? round($noShow / $total * 100, 1) : 0, 'average_party_size' => $total ? round($covers / $total, 1) : 0],
            'customers' => ['new' => max(0, $unique - $returning), 'returning' => $returning, 'repeat_rate' => $unique ? round($returning / $unique * 100, 1) : 0],
            'reviews' => ['count' => (clone $reviews)->count(), 'average_rating' => $averageRating === null ? null : round((float) $averageRating, 2)],
            'preorders' => ['orders' => $preorderIds->count(), 'items_quantity' => (int) (clone $preorderItems)->sum('quantity'),
                'known_value_cents' => (int) (clone $preorderItems)->whereNotNull('subtotal')->sum('subtotal')],
            'trends' => ['days' => $days, 'time_slots' => $timeSlots],
            'funnel' => ['activity_views' => $views, 'availability_views' => $availabilityViews, 'reservations' => $created,
                'view_to_reservation_rate' => $views ? round($created / $views * 100, 1) : 0,
                'attendance' => $completed, 'no_show' => $noShow, 'reviews' => $reviewEvents, 'returns' => $returning]];
    }

    private function period(array $filters): array
    {
        $key = $filters['period'] ?? '30';
        if ($key === 'custom') {
            return [CarbonImmutable::parse($filters['from'])->startOfDay(), CarbonImmutable::parse($filters['to'])->endOfDay(), $key];
        }
        $days = (int) $key;

        return [CarbonImmutable::today()->subDays($days - 1)->startOfDay(), CarbonImmutable::today()->endOfDay(), $key];
    }

    private function timeSlot(int $hour): string
    {
        return match (true) {
            $hour < 12 => 'Mattina', $hour < 15 => 'Pranzo', $hour < 19 => 'Pomeriggio', default => 'Cena'
        };
    }
}
