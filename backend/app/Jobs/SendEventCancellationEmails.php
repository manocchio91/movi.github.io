<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventCancellationEmails implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        Notification::query()
            ->where('event_id', $this->eventId)
            ->where('kind', 'event_cancelled')
            ->with('deliveries')
            ->get()
            ->each(function (Notification $notification): void {
                foreach ($notification->deliveries as $delivery) {
                    $delivery->forceFill([
                        'status' => 'queued',
                        'retry_count' => $delivery->retry_count,
                    ])->save();
                }
            });
    }
}
