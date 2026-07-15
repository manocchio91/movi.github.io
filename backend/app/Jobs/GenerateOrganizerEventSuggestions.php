<?php

namespace App\Jobs;

use App\Models\OrganizerProfile;
use App\Services\AiEventSuggestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateOrganizerEventSuggestions implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId, public array $filters = []) {}

    public function handle(AiEventSuggestionService $suggestions): void
    {
        $profile = OrganizerProfile::query()->find($this->organizerProfileId);

        if ($profile) {
            $suggestions->generate($profile, $this->filters);
        }
    }
}
