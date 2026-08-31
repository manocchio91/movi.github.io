<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsRecorder
{
    public function record(string $type, ?Event $event = null, ?User $user = null, ?string $deduplicationKey = null, array $metadata = [], ?int $organizerProfileId = null): void
    {
        AnalyticsEvent::query()->insertOrIgnore([
            'organizer_profile_id' => $event !== null ? $event->organizer_profile_id : $organizerProfileId,
            'event_id' => $event?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'deduplication_key' => $deduplicationKey,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function recordDiscovery(Event $event, string $type, ?User $user, ?string $sessionId): void
    {
        if ($sessionId === null || ! in_array($type, ['event_impression', 'event_detail_view'], true)) {
            return;
        }

        $sessionHash = hash('sha256', $sessionId);
        $day = now()->toDateString();
        $this->record($type, $event, $user, "{$type}:{$event->id}:{$sessionHash}:{$day}");

        if ($type === 'event_detail_view') {
            DB::table('experience_views')->insertOrIgnore([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'session_id' => $sessionHash,
                'source' => 'event_detail',
                'viewed_at' => now(),
                'view_date' => $day,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
