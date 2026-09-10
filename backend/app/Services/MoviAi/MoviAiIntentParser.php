<?php

namespace App\Services\MoviAi;

use App\Models\Category;
use Carbon\CarbonImmutable;
use Throwable;

final class MoviAiIntentParser
{
    public function __construct(private readonly AiIntentProvider $provider) {}

    public function parse(string $message, ?array $previous = null): array
    {
        $fallback = false;
        try {
            $intent = StructuredIntent::from($this->provider->parse($message, $this->schema()));
        } catch (Throwable) {
            $intent = $this->fallback($message, $previous);
            $fallback = true;
        }

        return ['intent' => $intent, 'fallback_used' => $fallback];
    }

    private function fallback(string $message, ?array $previous): StructuredIntent
    {
        $text = mb_strtolower(trim($message));
        $data = $previous ?? [];
        $now = CarbonImmutable::now(config('app.timezone'));
        if (str_contains($text, 'stasera')) {
            $data['when'] = 'tonight';
            $data['date'] = $now->toDateString();
            $data['plan_type'] = 'evening';
            $data['time_window'] = ['start' => '18:00', 'end' => '23:59'];
        } elseif (str_contains($text, 'domani')) {
            $data['when'] = 'tomorrow';
            $data['date'] = $now->addDay()->toDateString();
        } elseif (str_contains($text, 'oggi')) {
            $data['when'] = 'today';
            $data['date'] = $now->toDateString();
        } elseif (str_contains($text, 'weekend')) {
            $data['when'] = 'weekend';
        } elseif (preg_match('/\b(sabato|domenica)\b/u', $text, $day)) {
            $target = $now->next($day[1] === 'sabato' ? 6 : 0);
            $data['when'] = 'explicit_date';
            $data['date'] = $target->toDateString();
        } elseif (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?\b/', $text, $date)) {
            $data['when'] = 'explicit_date';
            $data['date'] = CarbonImmutable::create((int) ($date[3] ?? $now->year), (int) $date[2], (int) $date[1], timezone: config('app.timezone'))->toDateString();
        }
        if (preg_match('/(?:entro|max|budget)\s*(?:di\s*)?(\d+(?:[,.]\d{1,2})?)\s*(?:€|euro)/u', $text, $budget)) {
            $data['budget']['total_cents'] = (int) round((float) str_replace(',', '.', $budget[1]) * 100);
        }
        if (preg_match('/(?:entro|raggio(?:\s+di)?)\s*(\d+(?:[,.]\d+)?)\s*km/u', $text, $radius)) {
            $data['location']['radius_km'] = (float) str_replace(',', '.', $radius[1]);
        }
        if (preg_match('/\b(?:a|ad|vicino a)\s+([A-ZÀ-Ý][\p{L}\' -]{1,60})/u', trim($message), $city)) {
            $data['location']['city'] = trim($city[1]);
        }
        if (preg_match('/\b(bambin[io]|famiglia)\b/u', $text)) {
            $data['party']['intent'] = 'family';
            $data['preferences']['family'] = true;
        } elseif (preg_match('/\b(moglie|marito|compagna|compagno|coppia)\b/u', $text)) {
            $data['party']['intent'] = 'couple';
            $data['party']['adults'] = 2;
        } elseif (preg_match('/\b(amici|amiche)\b/u', $text)) {
            $data['party']['intent'] = 'friends';
        }
        if (preg_match('/\b(?:in|per|siamo)\s+(\d{1,2})\s+(?:persone|adulti|coperti)\b/u', $text, $party)) {
            $data['party']['adults'] = (int) $party[1];
        }
        if (str_contains($text, 'cena')) {
            $data['time_window'] = ['start' => '18:00', 'end' => '23:59'];
            $data['plan_type'] = 'evening';
        } elseif (str_contains($text, 'pranzo')) {
            $data['time_window'] = ['start' => '11:00', 'end' => '15:30'];
        }
        $categories = Category::query()->get(['slug', 'name']);
        $data['interests'] = $categories->filter(fn (Category $category) => str_contains($text, mb_strtolower($category->name)) || str_contains($text, mb_strtolower($category->slug)))
            ->map(fn (Category $category) => ['id' => null, 'slug' => $category->slug])->values()->all();

        return StructuredIntent::from($data);
    }

    private function schema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['when', 'time_window', 'party', 'location', 'interests', 'budget', 'plan_type', 'preferences', 'max_steps']];
    }
}
