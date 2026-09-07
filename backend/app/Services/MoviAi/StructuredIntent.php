<?php

namespace App\Services\MoviAi;

use InvalidArgumentException;

final readonly class StructuredIntent
{
    public function __construct(public array $data)
    {
        if (! in_array($data['when'] ?? null, [null, 'today', 'tonight', 'tomorrow', 'weekend', 'explicit_date'], true)) {
            throw new InvalidArgumentException('Invalid when value.');
        }
        if (($data['max_steps'] ?? 1) < 1 || ($data['max_steps'] ?? 1) > 3) {
            throw new InvalidArgumentException('Invalid max_steps value.');
        }
        if (! in_array($data['plan_type'], ['evening', 'half_day', 'full_day', 'generic'], true)
            || ! in_array($data['party']['intent'], [null, 'couple', 'family', 'friends'], true)
            || ! is_array($data['interests']) || ! is_int($data['max_steps'])) {
            throw new InvalidArgumentException('Invalid structured intent.');
        }
    }

    public static function from(array $data): self
    {
        $defaults = ['when' => null, 'date' => null, 'time_window' => ['start' => null, 'end' => null],
            'party' => ['adults' => null, 'children' => null, 'intent' => null],
            'location' => ['city' => null, 'latitude' => null, 'longitude' => null, 'radius_km' => null],
            'interests' => [], 'budget' => ['total_cents' => null], 'plan_type' => 'generic',
            'preferences' => ['indoor_outdoor' => null, 'family' => null, 'mix' => null], 'max_steps' => 3];

        $merged = array_replace_recursive($defaults, array_intersect_key($data, $defaults));
        foreach (['time_window', 'party', 'location', 'budget', 'preferences'] as $key) {
            $merged[$key] = array_intersect_key($merged[$key], $defaults[$key]);
        }
        $merged['interests'] = array_values(array_filter($merged['interests'], fn ($interest) => is_array($interest) && (isset($interest['slug']) || isset($interest['id']))));
        if (($merged['location']['radius_km'] !== null && (! is_numeric($merged['location']['radius_km']) || $merged['location']['radius_km'] < 1 || $merged['location']['radius_km'] > 200))
            || ($merged['budget']['total_cents'] !== null && (! is_int($merged['budget']['total_cents']) || $merged['budget']['total_cents'] < 0))) {
            throw new InvalidArgumentException('Invalid structured intent values.');
        }

        return new self($merged);
    }
}
