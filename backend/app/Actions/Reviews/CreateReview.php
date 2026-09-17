<?php

namespace App\Actions\Reviews;

use App\Jobs\RecalculateReviewStatistics;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Support\Str;

class CreateReview
{
    public function handle(User $user, Event $event, array $data): Review
    {
        $images = $data['images'] ?? [];
        unset($data['images']);

        abort_if($event->organizerProfile?->owner_user_id === $user->id, 403, 'Un organizzatore non puo recensire i propri eventi.');

        $booking = Booking::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->when(isset($data['booking_id']), fn ($query) => $query->whereKey($data['booking_id']))
            ->first();

        abort_unless($booking !== null, 403, 'Serve una prenotazione confermata per recensire.');
        $eventConcludedAt = $event->ends_at ?? $event->starts_at;
        abort_unless($eventConcludedAt->isPast(), 403, 'Puoi recensire solo dopo la conclusione dell’evento.');
        abort_if(Review::query()->where('booking_id', $booking->id)->exists(), 422, 'Prenotazione gia recensita.');
        abort_if(Review::query()->where('event_id', $event->id)->where('user_id', $user->id)->exists(), 422, 'Hai gia recensito questa esperienza.');

        $review = Review::query()->create([
            ...$data,
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'organizer_profile_id' => $event->organizer_profile_id,
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        foreach ($images as $image) {
            ReviewImage::query()->create([
                'review_id' => $review->id,
                'disk' => $image['disk'] ?? 's3',
                'path' => $image['path'],
                'mime_type' => $image['mime_type'] ?? null,
                'size_bytes' => $image['size_bytes'] ?? null,
            ]);
        }

        RecalculateReviewStatistics::dispatch($event->id, $event->organizer_profile_id);
        app(AnalyticsRecorder::class)->record('review_created', $event, $user, "review_created:{$review->id}");

        return $review->load(['event', 'images']);
    }
}
