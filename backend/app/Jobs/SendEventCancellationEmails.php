<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Services\OperationalEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendEventCancellationEmails implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(OperationalEmailService $emails): void
    {
        Notification::query()
            ->where('event_id', $this->eventId)
            ->where('kind', 'event_cancelled')
            ->with('deliveries')
            ->get()
            ->each(function (Notification $notification) use ($emails): void {
                foreach ($notification->deliveries as $delivery) {
                    DB::transaction(function () use ($delivery, $emails, $notification): void {
                        $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
                        if ($locked->sent_at !== null) {
                            return;
                        }

                        $sent = $emails->eventCancelled($notification, $locked->recipient);
                        $locked->forceFill($sent ? [
                            'status' => 'sent', 'sent_at' => now(), 'last_error' => null,
                        ] : [
                            'status' => 'failed', 'retry_count' => $locked->retry_count + 1,
                            'last_error' => 'Operational email could not be queued.',
                        ])->save();
                    });
                }
            });
    }
}
