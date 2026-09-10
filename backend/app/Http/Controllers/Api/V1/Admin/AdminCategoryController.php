<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminCategoryController extends Controller
{
    public function store(StoreAdminCategoryRequest $request): JsonResponse
    {
        $name = (string) $request->validated('name');
        $slug = Str::slug($name);
        if ($slug === '' || Category::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->orWhere('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'La categoria esiste già o non è valida.']);
        }

        $category = Category::query()->create(['name' => $name, 'slug' => $slug, 'is_active' => true]);

        return response()->json(['data' => $category], 201);
    }
}
