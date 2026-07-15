<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::from($data['role'] ?? 'customer'),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('pwa')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            return response()->json(['message' => 'Credenziali non valide.'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('pwa')->plainTextToken,
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout eseguito.']);
    }

    public function me(): JsonResponse
    {
        return response()->json(['user' => request()->user()]);
    }
}
