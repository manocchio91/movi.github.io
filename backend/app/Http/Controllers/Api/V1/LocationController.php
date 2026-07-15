<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        $locations = Municipality::query()
            ->with('province')
            ->when($query !== '', fn ($builder) => $builder->where('name', 'ilike', "%{$query}%"))
            ->limit(20)
            ->get();

        return response()->json(['data' => $locations]);
    }
}
