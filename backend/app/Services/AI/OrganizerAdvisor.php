<?php

namespace App\Services\AI;

interface OrganizerAdvisor
{
    public function forecastAttendance(array $context): array;

    public function suggestBestDate(array $context): array;

    public function suggestPricing(array $context): array;
}
