<?php

namespace App\Services\MoviAi;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ConfiguredAiIntentProvider implements AiIntentProvider
{
    public function parse(string $message, array $schema): array
    {
        if (config('ai.provider') === 'none' || ! config('ai.api_key') || ! config('ai.endpoint')) {
            throw new RuntimeException('AI provider is not configured.');
        }
        $response = Http::timeout(config('ai.timeout'))->withToken(config('ai.api_key'))->post(config('ai.endpoint'), [
            'model' => config('ai.model'), 'input' => $message, 'schema' => $schema,
        ])->throw()->json('intent');
        if (! is_array($response)) {
            throw new RuntimeException('AI provider returned invalid structured output.');
        }

        return $response;
    }
}
