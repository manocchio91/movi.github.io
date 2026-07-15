<?php

namespace App\Services\AI;

class DeterministicOrganizerAdvisor implements OrganizerAdvisor
{
    public function forecastAttendance(array $context): array
    {
        return ['status' => 'not_implemented', 'message' => 'Predisposto per previsione affluenza futura.'];
    }

    public function suggestBestDate(array $context): array
    {
        return ['status' => 'not_implemented', 'message' => 'Predisposto per suggerimento data migliore.'];
    }

    public function suggestPricing(array $context): array
    {
        return ['status' => 'not_implemented', 'message' => 'Predisposto per ottimizzazione prezzi futura.'];
    }
}
