<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindMunicipalitiesByPostalCodeRequest;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function municipalities(FindMunicipalitiesByPostalCodeRequest $request): JsonResponse
    {
        $municipalities = Municipality::query()
            ->with('province:id,code')
            ->whereHas('province.region', fn ($query) => $query->where('code', config('territory.region_code')))
            ->where('postal_code', $request->validated('postal_code'))
            ->orderBy('name')
            ->get()
            ->map(fn (Municipality $municipality) => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'province' => $municipality->province?->code,
                'postal_code' => $municipality->postal_code,
            ]);

        return response()->json(['data' => $municipalities]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        $locations = Municipality::query()
            ->with('province')
            ->whereHas('province.region', fn ($builder) => $builder->where('code', config('territory.region_code')))
            ->when($query !== '', fn ($builder) => $builder->where('name', 'ilike', "%{$query}%"))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json(['data' => $locations]);
    }
}
