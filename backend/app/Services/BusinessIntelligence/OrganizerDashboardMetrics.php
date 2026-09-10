<?php

namespace App\Services\BusinessIntelligence;

use App\Enums\EventStatus;
use App\Models\OrganizerProfile;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class OrganizerDashboardMetrics
{
    private const VALID_BOOKING_STATUSES = ['confirmed', 'checked_in', 'completed'];

    public const OPERATIONAL_EVENT_STATUSES = [EventStatus::Published->value, EventStatus::SoldOut->value];

    public const UNPUBLISHED_EVENT_STATUSES = [
        EventStatus::Draft->value,
        EventStatus::PendingReview->value,
        EventStatus::Rejected->value,
        EventStatus::Suspended->value,
        EventStatus::Archived->value,
    ];

    public function calculate(OrganizerProfile $profile): array
    {
        $eventIds = $profile->events()->pluck('id');
        $events = $profile->events();
        $operationalFutureEvents = $profile->events()
            ->whereIn('status', self::OPERATIONAL_EVENT_STATUSES)
            ->where('starts_at', '>=', now());
        $bookings = DB::table('bookings')
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', self::VALID_BOOKING_STATUSES);
        $participants = (clone $bookings)->select('user_id')->distinct()->pluck('user_id');
        $returningParticipants = DB::table('bookings')
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', self::VALID_BOOKING_STATUSES)
            ->select('user_id', DB::raw('count(*) as bookings_count'))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= 2')
            ->count();
        $uniqueParticipants = $participants->count();
        $analytics = DB::table('analytics_events')->where('organizer_profile_id', $profile->id);
        $impressions = (clone $analytics)->where('type', 'event_impression')->count();
        $detailViews = (clone $analytics)->where('type', 'event_detail_view')->count();
        $shares = (clone $analytics)->where('type', 'event_share')->count();
        $waitlistJoined = (clone $analytics)->where('type', 'waitlist_joined')->count();
        $waitlistConverted = (clone $analytics)->where('type', 'waitlist_converted')->count();
        $followersAcquired = (clone $analytics)->where('type', 'organizer_followed')->count();
        $bookingsReceived = (clone $bookings)->count();
        $participantsTotal = (int) (clone $bookings)->sum('total_people');
        $cancellations = DB::table('bookings')->whereIn('event_id', $eventIds)->where('status', 'like', 'cancelled%')->count();
        $checkedInPeople = (int) DB::table('bookings')->join('check_ins', 'check_ins.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.event_id', $eventIds)->sum('bookings.total_people');
        $noShowPeople = (int) DB::table('bookings')->join('events', 'events.id', '=', 'bookings.event_id')
            ->leftJoin('check_ins', 'check_ins.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.event_id', $eventIds)->whereIn('bookings.status', self::VALID_BOOKING_STATUSES)
            ->where('events.starts_at', '<', now())->whereNull('check_ins.id')->sum('bookings.total_people');
        $reviewsCount = DB::table('reviews')->whereIn('event_id', $eventIds)->where('status', 'approved')->whereNull('hidden_at')->count();

        return [
            'total_events' => (clone $events)->count(),
            'published_events' => (clone $events)->where('status', EventStatus::Published->value)->count(),
            'future_events' => (clone $operationalFutureEvents)->count(),
            'completed_events' => (clone $events)->where('status', EventStatus::Completed->value)->count(),
            'unpublished_events' => (clone $events)->whereIn('status', self::UNPUBLISHED_EVENT_STATUSES)->count(),
            'cancelled_events' => (clone $events)->where('status', EventStatus::Cancelled->value)->count(),
            'sold_out_events' => (clone $events)->where('status', EventStatus::SoldOut->value)->count(),
            'available_places' => (clone $operationalFutureEvents)->sum('available_total'),
            'occupied_places' => (clone $operationalFutureEvents)->sum('capacity_total') - (clone $operationalFutureEvents)->sum('available_total'),
            'bookings_received' => $bookingsReceived,
            'participants_total' => $participantsTotal,
            'cancellations' => $cancellations,
            'check_in_people' => $checkedInPeople,
            'no_show_people' => $noShowPeople,
            'impressions' => $impressions,
            'detail_views' => $detailViews,
            'shares' => $shares,
            'waitlist_joined' => $waitlistJoined,
            'waitlist_converted' => $waitlistConverted,
            'followers_acquired' => $followersAcquired,
            'booking_conversion_rate' => $detailViews > 0 ? round(($bookingsReceived / $detailViews) * 100, 2) : 0,
            'attendance_rate' => $participantsTotal > 0 ? round(($checkedInPeople / $participantsTotal) * 100, 2) : 0,
            'cancellation_rate' => ($bookingsReceived + $cancellations) > 0 ? round(($cancellations / ($bookingsReceived + $cancellations)) * 100, 2) : 0,
            'no_show_rate' => $participantsTotal > 0 ? round(($noShowPeople / $participantsTotal) * 100, 2) : 0,
            'review_rate' => $checkedInPeople > 0 ? round(($reviewsCount / $checkedInPeople) * 100, 2) : 0,
            'follow_conversion_rate' => $detailViews > 0 ? round(($followersAcquired / $detailViews) * 100, 2) : 0,
            'unique_participants' => $uniqueParticipants,
            'returning_participants' => $returningParticipants,
            'returning_rate' => $uniqueParticipants > 0 ? round(($returningParticipants / $uniqueParticipants) * 100, 2) : 0,
            'new_customers_rate' => $uniqueParticipants > 0 ? round((($uniqueParticipants - $returningParticipants) / $uniqueParticipants) * 100, 2) : 0,
            'followers_total' => $profile->followers()->count(),
            'new_followers' => $profile->followers()->wherePivot('created_at', '>=', now()->subMonth())->count(),
            'reviews' => $reviewsCount,
            'average_rating' => DB::table('reviews')->whereIn('event_id', $eventIds)->where('status', 'approved')->whereNull('hidden_at')->avg('rating'),
            'review_analytics' => $this->reviewAnalytics($profile),
            'views_total' => $detailViews,
        ];
    }

    public function reviewAnalytics(OrganizerProfile $profile): array
    {
        $eventIds = $profile->events()->pluck('id');
        $reviews = DB::table('reviews')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'approved')
            ->whereNull('hidden_at');
        $reviewsCount = (clone $reviews)->count();

        return [
            'average_rating' => (clone $reviews)->avg('rating'),
            'reviews_count' => $reviewsCount,
            'satisfied_rate' => $reviewsCount > 0 ? round(((clone $reviews)->where('rating', '>=', 4)->count() / $reviewsCount) * 100, 2) : 0,
            'recommendation_rate' => $reviewsCount > 0 ? round(((clone $reviews)->where('would_recommend', true)->count() / $reviewsCount) * 100, 2) : 0,
            'trend' => (clone $reviews)
                ->selectRaw('date(reviewed_at) as day, count(*) as reviews_count, avg(rating) as average_rating')
                ->groupByRaw('date(reviewed_at)')
                ->orderBy('day')
                ->limit(30)
                ->get()
                ->all(),
            'best_categories' => DB::table('reviews')
                ->join('events', 'events.id', '=', 'reviews.event_id')
                ->join('event_categories', 'event_categories.event_id', '=', 'events.id')
                ->join('categories', 'categories.id', '=', 'event_categories.category_id')
                ->where('events.organizer_profile_id', $profile->id)
                ->where('reviews.status', 'approved')
                ->whereNull('reviews.hidden_at')
                ->groupBy('categories.id', 'categories.name')
                ->selectRaw('categories.name, count(reviews.id) as reviews_count, avg(reviews.rating) as average_rating')
                ->orderByDesc('average_rating')
                ->limit(5)
                ->get()
                ->all(),
        ];
    }

    public function categoryPerformance(OrganizerProfile $profile): array
    {
        return DB::table('event_categories')
            ->join('categories', 'categories.id', '=', 'event_categories.category_id')
            ->join('events', 'events.id', '=', 'event_categories.event_id')
            ->leftJoin('bookings', function (JoinClause $join): void {
                $join->on('bookings.event_id', '=', 'events.id')
                    ->whereIn('bookings.status', self::VALID_BOOKING_STATUSES);
            })
            ->where('events.organizer_profile_id', $profile->id)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name, count(distinct events.id) as events_count, count(bookings.id) as bookings_count, coalesce(avg(bookings.total_people), 0) as avg_participants, coalesce(avg((events.capacity_total - events.available_total)::decimal / nullif(events.capacity_total,0) * 100),0) as fill_rate')
            ->orderByDesc('bookings_count')
            ->get()
            ->all();
    }
}
