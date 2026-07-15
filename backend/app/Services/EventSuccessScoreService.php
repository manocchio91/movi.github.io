<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSuccessScore;
use Illuminate\Support\Facades\DB;

class EventSuccessScoreService
{
    public function calculate(Event $event): EventSuccessScore
    {
        $booked = (int) $event->bookings()->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_people');
        $attended = (int) $event->bookings()
            ->where(function ($query): void {
                $query->whereIn('status', ['checked_in', 'completed'])
                    ->orWhereExists(function ($subquery): void {
                        $subquery->selectRaw('1')->from('check_ins')->whereColumn('check_ins.booking_id', 'bookings.id');
                    });
            })
            ->sum('total_people');
        $reviews = $event->reviews()->where('status', 'approved')->whereNull('hidden_at')->get();
        $reviewCount = $reviews->count();
        $fillRate = $event->capacity_total > 0 ? min(100, ($booked / $event->capacity_total) * 100) : 0;
        $attendanceRate = $booked > 0 ? ($attended / $booked) * 100 : 0;
        $noShowRate = $booked > 0 ? (($booked - $attended) / $booked) * 100 : 0;
        $averageRating = (float) $reviews->avg('rating');
        $recommendationRate = $reviewCount > 0 ? ($reviews->where('would_recommend', true)->count() / $reviewCount) * 100 : 0;
        $favoriteCount = DB::table('favorites')->where('event_id', $event->id)->count();

        $successScore = round(
            ($fillRate * 0.35)
            + ($attendanceRate * 0.25)
            + (($averageRating / 5) * 100 * 0.20)
            + ($recommendationRate * 0.10)
            + (min($favoriteCount, 50) * 0.20)
            - ($noShowRate * 0.20),
            2,
        );

        return EventSuccessScore::query()->updateOrCreate(['event_id' => $event->id], [
            'fill_rate' => round($fillRate, 2),
            'attendance_rate' => round($attendanceRate, 2),
            'no_show_rate' => round($noShowRate, 2),
            'average_rating' => round($averageRating, 2),
            'review_count' => $reviewCount,
            'recommendation_rate' => round($recommendationRate, 2),
            'favorite_count' => $favoriteCount,
            'success_score' => $successScore,
            'calculated_at' => now(),
        ]);
    }
}
