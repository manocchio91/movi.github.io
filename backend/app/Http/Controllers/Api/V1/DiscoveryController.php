<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoveryRequest;
use App\Http\Requests\RecommendationOpenRequest;
use App\Models\Activity;
use App\Models\Event;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Discovery\DiscoveryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class DiscoveryController extends Controller
{
    public function opened(RecommendationOpenRequest $request, AnalyticsRecorder $analytics): JsonResponse
    {
        $data = $request->validated();
        $key = 'recommendation_opened:'.$data['entity_type'].':'.$data['entity_id'].':'.now()->toDateString().':'.(auth()->id() ?? 'guest');
        if ($data['entity_type'] === 'event') {
            $analytics->record('recommendation_opened', Event::query()->where('ulid', $data['entity_id'])->firstOrFail(), $request->user(), $key);
        } else {
            $analytics->recordActivity('recommendation_opened', Activity::query()->where('ulid', $data['entity_id'])->firstOrFail(), $request->user(), deduplicationKey: $key);
        }

        return response()->json(['data' => ['recorded' => true]]);
    }

    public function discovery(DiscoveryRequest $request, DiscoveryEngine $engine, AnalyticsRecorder $analytics): JsonResponse
    {
        return $this->respond($request, $engine, $analytics, false);
    }

    public function recommendations(DiscoveryRequest $request, DiscoveryEngine $engine, AnalyticsRecorder $analytics): JsonResponse
    {
        return $this->respond($request, $engine, $analytics, true);
    }

    private function respond(DiscoveryRequest $request, DiscoveryEngine $engine, AnalyticsRecorder $analytics, bool $recommended): JsonResponse
    {
        $rows = $engine->search($request->validated(), $request->user(), $recommended);
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 15);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $session = trim((string) $request->header('X-Analytics-Session'));
        $hash = $session === '' ? 'anonymous' : hash('sha256', mb_substr($session, 0, 128));
        $type = $recommended ? 'recommendation_impression' : ($request->query() === [] ? 'discovery_view' : 'discovery_filter_applied');
        $analytics->record($type, user: $request->user(), deduplicationKey: "{$type}:{$hash}:".now()->toDateString(), metadata: ['filters' => array_keys($request->validated())]);

        return response()->json($paginator);
    }
}
