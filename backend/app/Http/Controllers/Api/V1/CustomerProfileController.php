<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['bookings', 'favorites', 'reviews']);

        return response()->json(['data' => $user->only(['ulid', 'name', 'email', 'phone', 'created_at', 'bookings_count', 'favorites_count', 'reviews_count'])]);
    }

    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(['data' => $user->only(['ulid', 'name', 'email', 'phone', 'created_at'])]);
    }

    public function password(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return response()->json(['message' => 'Password aggiornata.']);
    }
}
