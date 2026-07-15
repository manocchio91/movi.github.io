<?php

namespace App\Services\BusinessIntelligence;

use App\Enums\EventStatus;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\DB;

class OrganizerDashboardMetrics
{
    public function calculate(OrganizerProfile $profile): array
    {
        $eventIds = $profile->events()->pluck('id');
        $events = $profile->events();
        $bookings = DB::table('bookings')->whereIn('event_id', $eventIds)->where('status', 'confirmed');
        $participants = (clone $bookings)->select('user_id')->distinct()->pluck('user_id');
        $returningParticipants = DB::table('bookings')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'confirmed')
            ->select('user_id', DB::raw('count(*) as bookings_count'))
            ->groupBy('user_id')
            ->having('bookings_count', '>=', 2)
            ->count();
        $uniqueParticipants = $participants->count();

        return [
            'published_events' => (clone $events)->where('status', EventStatus::Published->value)->count(),
            'future_events' => (clone $events)->where('starts_at', '>=', now())->count(),
            'completed_events' => (clone $events)->where('status', EventStatus::Completed->value)->count(),
            'cancelled_events' => (clone $events)->where('status', EventStatus::Cancelled->value)->count(),
            'sold_out_events' => (clone $events)->where('status', EventStatus::SoldOut->value)->count(),
            'available_places' => (clone $events)->sum('available_total'),
            'occupied_places' => (clone $events)->sum('capacity_total') - (clone $events)->sum('available_total'),
            'bookings_received' => (clone $bookings)->count(),
            'participants_total' => (clone $bookings)->sum('total_people'),
            'unique_participants' => $uniqueParticipants,
            'returning_participants' => $returningParticipants,
            'returning_rate' => $uniqueParticipants > 0 ? round(($returningParticipants / $uniqueParticipants) * 100, 2) : 0,
            'new_customers_rate' => $uniqueParticipants > 0 ? round((($uniqueParticipants - $returningParticipants) / $uniqueParticipants) * 100, 2) : 0,
            'followers_total' => $profile->followers()->count(),
            'new_followers' => $profile->followers()->wherePivot('created_at', '>=', now()->subMonth())->count(),
            'reviews' => DB::table('reviews')->whereIn('event_id', $eventIds)->count(),
            'average_rating' => DB::table('reviews')->whereIn('event_id', $eventIds)->avg('rating'),
            'views_total' => DB::table('experience_views')->whereIn('event_id', $eventIds)->count(),
        ];
    }

    public function categoryPerformance(OrganizerProfile $profile): array
    {
        return DB::table('event_categories')
            ->join('categories', 'categories.id', '=', 'event_categories.category_id')
            ->join('events', 'events.id', '=', 'event_categories.event_id')
            ->leftJoin('bookings', 'bookings.event_id', '=', 'events.id')
            ->where('events.organizer_profile_id', $profile->id)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name, count(distinct events.id) as events_count, count(bookings.id) as bookings_count, coalesce(avg(bookings.total_people), 0) as avg_participants, coalesce(avg((events.capacity_total - events.available_total)::decimal / nullif(events.capacity_total,0) * 100),0) as fill_rate')
            ->orderByDesc('bookings_count')
            ->get()
            ->all();
    }
}
