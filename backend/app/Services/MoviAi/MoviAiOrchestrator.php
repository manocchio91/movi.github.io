<?php

namespace App\Services\MoviAi;

use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Planning\PlanningEngine;

final class MoviAiOrchestrator
{
    public function __construct(private readonly MoviAiIntentParser $parser, private readonly PlanningEngine $planning, private readonly AnalyticsRecorder $analytics) {}

    public function handle(string $message, ?array $previous, ?User $user): array
    {
        $this->analytics->record('ai_query_submitted', user: $user, metadata: ['length_bucket' => min(5, intdiv(mb_strlen($message), 40))]);
        $parsed = $this->parser->parse($message, $previous);
        $intent = $parsed['intent']->data;
        $this->analytics->record('ai_intent_parsed', user: $user, metadata: ['provider' => $parsed['fallback_used'] ? 'fallback' : config('ai.provider')]);
        if ($parsed['fallback_used']) {
            $this->analytics->record('ai_fallback_used', user: $user);
        }
        $request = $this->toPlanRequest($intent);
        $warnings = [];
        if (($intent['location']['city'] ?? null) && ! isset($request['municipality_id'])) {
            $warnings[] = 'Località non riconosciuta.';
        }
        if (($intent['location']['radius_km'] ?? null) && (! isset($request['latitude']) || ! isset($request['longitude']))) {
            $warnings[] = 'Coordinate mancanti: il raggio non è applicabile.';
        }
        if ($request === []) {
            return $this->response($message, $intent, [], ['Dove vuoi cercare?'], 'location');
        }
        $plans = $this->planning->generate($request, $user);
        if ($plans === []) {
            $warnings[] = 'Nessun risultato compatibile.';
            $this->analytics->record('ai_no_results', user: $user);
        } else {
            $this->analytics->record('ai_plan_generated', user: $user, metadata: ['plans_count' => count($plans)]);
        }
        if (collect($plans)->contains(fn (array $plan) => $plan['budget_verification'] === 'partial')) {
            $warnings[] = 'Budget parzialmente verificabile.';
        }

        return $this->response($message, $intent, $plans, $warnings);
    }

    private function toPlanRequest(array $intent): array
    {
        $request = array_filter(['date' => $intent['date'], 'start_time' => $intent['time_window']['start'], 'end_time' => $intent['time_window']['end'],
            'latitude' => $intent['location']['latitude'], 'longitude' => $intent['location']['longitude'], 'radius_km' => $intent['location']['radius_km'],
            'budget_cents' => $intent['budget']['total_cents'], 'with' => $intent['party']['intent'], 'intent' => $intent['plan_type'], 'max_steps' => $intent['max_steps']], fn ($value) => $value !== null);
        $request['party_size'] = max(1, (int) ($intent['party']['adults'] ?? 1) + (int) ($intent['party']['children'] ?? 0));
        if ($intent['location']['city']) {
            $municipality = Municipality::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($intent['location']['city'])])->first();
            if ($municipality) {
                $request['municipality_id'] = $municipality->id;
            }
        }
        if (isset($intent['interests'][0]['slug'])) {
            $category = Category::query()->where('slug', $intent['interests'][0]['slug'])->first();
            if ($category) {
                $request['category_id'] = $category->id;
            }
        }

        return $request;
    }

    private function response(string $query, array $intent, array $plans, array $warnings, ?string $clarification = null): array
    {
        $parts = array_filter([$intent['date'] ? 'per '.$intent['date'] : null, $intent['location']['city'] ? 'a '.$intent['location']['city'] : null,
            $intent['location']['radius_km'] ? 'entro '.$intent['location']['radius_km'].' km' : null, $intent['budget']['total_cents'] !== null ? 'con budget massimo '.number_format($intent['budget']['total_cents'] / 100, 2, ',', '.').' €' : null]);

        return ['query' => $query, 'parsed_intent' => $intent, 'interpretation_summary' => 'Ho cercato proposte'.($parts ? ' '.implode(' ', $parts) : '').'.',
            'plans' => $plans, 'warnings' => array_values(array_unique($warnings)), 'availability_checked_at' => now()->toIso8601String(), 'clarification_needed' => $clarification];
    }
}
