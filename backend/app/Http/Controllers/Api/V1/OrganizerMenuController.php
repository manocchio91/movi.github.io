<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMenuRequest;
use App\Http\Requests\UpsertMenuItemRequest;
use App\Http\Requests\UpsertMenuRequest;
use App\Http\Requests\UpsertMenuSectionRequest;
use App\Http\Resources\MenuResource;
use App\Models\Activity;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Menu\MenuImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OrganizerMenuController extends Controller
{
    private function own(Activity $activity, ?Menu $menu = null): void
    {
        $this->authorize('update', $activity);
        if ($menu && $menu->activity_id !== $activity->id) {
            abort(404);
        }
    }

    public function index(Activity $activity): JsonResponse
    {
        $this->own($activity);

        return response()->json(['data' => MenuResource::collection($activity->menus()->with('sections.items')->get())]);
    }

    public function store(UpsertMenuRequest $request, Activity $activity): JsonResponse
    {
        $this->own($activity);
        $menu = $activity->menus()->create(['ulid' => (string) Str::ulid(), 'status' => 'draft', ...$request->validated()]);

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))], 201);
    }

    public function update(UpsertMenuRequest $request, Activity $activity, Menu $menu): JsonResponse
    {
        $this->own($activity, $menu);
        $menu->update($request->validated());

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))]);
    }

    public function section(UpsertMenuSectionRequest $request, Activity $activity, Menu $menu): JsonResponse
    {
        $this->own($activity, $menu);
        $menu->sections()->create(['ulid' => (string) Str::ulid(), ...$request->validated()]);

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))], 201);
    }

    public function updateSection(UpsertMenuSectionRequest $request, Activity $activity, Menu $menu, MenuSection $section): JsonResponse
    {
        $this->own($activity, $menu);
        abort_unless($section->menu_id === $menu->id, 404);
        $section->update($request->validated());

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))]);
    }

    public function item(UpsertMenuItemRequest $request, Activity $activity, Menu $menu, MenuSection $section): JsonResponse
    {
        $this->own($activity, $menu);
        abort_unless($section->menu_id === $menu->id, 404);
        $section->items()->create(['ulid' => (string) Str::ulid(), ...$request->validated()]);

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))], 201);
    }

    public function updateItem(UpsertMenuItemRequest $request, Activity $activity, Menu $menu, MenuItem $item): JsonResponse
    {
        $this->own($activity, $menu);
        abort_unless($item->section->getAttribute('menu_id') === $menu->id, 404);
        $item->update($request->validated());

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))]);
    }

    public function publish(Activity $activity, Menu $menu, AnalyticsRecorder $analytics): JsonResponse
    {
        $this->own($activity, $menu);
        abort_if(! $menu->sections()->whereHas('items')->exists(), 422, 'Il menu deve contenere almeno un piatto.');
        $activity->menus()->where('id', '!=', $menu->id)->update(['status' => 'draft']);
        $menu->update(['status' => 'published', 'active' => true]);
        $analytics->recordActivity('menu_published', $activity, request()->user());

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))]);
    }

    public function unpublish(Activity $activity, Menu $menu): JsonResponse
    {
        $this->own($activity, $menu);
        $menu->update(['status' => 'draft']);

        return response()->json(['data' => MenuResource::make($menu->load('sections.items'))]);
    }

    public function destroy(Activity $activity, Menu $menu): JsonResponse
    {
        $this->own($activity, $menu);
        $menu->delete();

        return response()->json(['data' => null]);
    }

    public function destroySection(Activity $activity, Menu $menu, MenuSection $section): JsonResponse
    {
        $this->own($activity, $menu);
        abort_unless($section->menu_id === $menu->id, 404);
        $section->delete();

        return response()->json(['data' => null]);
    }

    public function destroyItem(Activity $activity, Menu $menu, MenuItem $item): JsonResponse
    {
        $this->own($activity, $menu);
        abort_unless($item->section->getAttribute('menu_id') === $menu->id, 404);
        $item->delete();

        return response()->json(['data' => null]);
    }

    public function import(ImportMenuRequest $request, Activity $activity, MenuImportService $importer, AnalyticsRecorder $analytics): JsonResponse
    {
        $this->own($activity);
        $result = $importer->preview($request->file('file'));
        $analytics->recordActivity('menu_import_previewed', $activity, $request->user());

        return response()->json(['data' => $result]);
    }

    public function confirmImport(Activity $activity, MenuImportService $importer, AnalyticsRecorder $analytics): JsonResponse
    {
        $this->own($activity);
        $data = request()->validate([
            'name' => ['nullable', 'string', 'max:120'], 'mode' => ['required', 'in:add,replace'],
            'sections' => ['required', 'array', 'min:1', 'max:100'], 'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.items' => ['required', 'array', 'min:1', 'max:500'], 'sections.*.items.*.name' => ['required', 'string', 'max:160'],
            'sections.*.items.*.description' => ['nullable', 'string', 'max:1000'], 'sections.*.items.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'sections.*.items.*.available' => ['required', 'boolean'], 'sections.*.items.*.allergens' => ['nullable', 'array'],
        ]);
        $result = $importer->confirm($activity, $data['sections'], $data['name'] ?? null, $data['mode']);
        $analytics->recordActivity('menu_imported', $activity, request()->user());

        return response()->json(['data' => ['menu' => MenuResource::make($result['menu']), 'report' => $result['report']]], 201);
    }
}
