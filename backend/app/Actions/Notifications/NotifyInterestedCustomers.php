<?php

namespace App\Actions\Notifications;

use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Str;

class NotifyInterestedCustomers
{
    public function handle(Event $event): int
    {
        $event->loadMissing(['categories', 'organizerProfile', 'municipality']);
        $categoryIds = $event->categories->pluck('id');
        $sent = 0;

        User::query()
            ->where(function ($query) use ($categoryIds, $event): void {
                $query->where(function ($categoryAudience) use ($categoryIds): void {
                    $categoryAudience
                        ->whereHas('preferredCategories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds))
                        ->whereHas('notificationPreference', fn ($preference) => $preference->where('promotional_enabled', true));
                })->orWhere(function ($followerAudience) use ($event): void {
                    $followerAudience
                        ->whereHas('followedOrganizers', fn ($organizers) => $organizers->where('organizer_profiles.id', $event->organizer_profile_id))
                        ->whereHas('notificationPreference', fn ($preference) => $preference->where('followed_organizer_events_enabled', true));
                });
            })
            ->whereHas('notificationPreference', fn ($query) => $query
                ->where(function ($channels): void {
                    $channels->where('internal_enabled', true)->orWhere('email_enabled', true);
                }))
            ->with('notificationPreference')
            ->chunkById(100, function ($users) use ($event, &$sent): void {
                foreach ($users as $user) {
                    $preference = $user->notificationPreference;
                    $channels = [];

                    if ($preference?->internal_enabled) {
                        $channels[] = 'internal';
                    }

                    if ($preference?->email_enabled) {
                        $channels[] = 'email';
                    }

                    foreach ($channels as $channel) {
                        $notification = Notification::query()->firstOrCreate(
                            ['deduplication_key' => "event:{$event->id}:user:{$user->id}:{$channel}"],
                            [
                                'ulid' => (string) Str::ulid(),
                                'user_id' => $user->id,
                                'event_id' => $event->id,
                                'category_id' => $event->categories->first()?->id,
                                'kind' => 'event_published',
                                'channel' => $channel,
                                'template' => 'event_published_immediate',
                                'title' => "Nuovo evento: {$event->title}",
                                'message' => "{$event->organizerProfile->name} ha pubblicato {$event->title} a {$event->municipality->name}.",
                                'status' => 'sent',
                                'read' => false,
                                'payload' => [
                                    'event_ulid' => $event->ulid,
                                    'event_title' => $event->title,
                                    'organizer_name' => $event->organizerProfile->name,
                                    'starts_at' => $event->starts_at->toIso8601String(),
                                    'municipality' => $event->municipality->name,
                                    'price' => $event->is_free ? 'Gratis' : $event->price_notes,
                                    'available_total' => $event->available_total,
                                    'url' => "/events/{$event->ulid}",
                                ],
                                'sent_at' => now(),
                            ],
                        );

                        NotificationDelivery::query()->firstOrCreate(
                            [
                                'notification_id' => $notification->id,
                                'channel' => $channel,
                            ],
                            [
                                'status' => 'sent',
                                'recipient' => $channel === 'email' ? $user->email : (string) $user->id,
                                'retry_count' => 0,
                                'sent_at' => now(),
                            ],
                        );

                        if ($notification->wasRecentlyCreated) {
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }
}
