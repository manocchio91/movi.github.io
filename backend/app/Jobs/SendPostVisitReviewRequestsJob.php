<?php

namespace App\Jobs;

use App\Actions\Notifications\SendPostVisitReviewRequests;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendPostVisitReviewRequestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SendPostVisitReviewRequests $sender): void
    {
        $sender->handle();
    }
}
