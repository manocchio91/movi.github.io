<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterUser;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->execute($request->validated());

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->createToken('pwa')->plainTextToken,
            'redirect_to' => $this->redirectPath($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt($request->validated())) {
            return response()->json(['message' => 'Credenziali non valide.'], 422);
        }

        /** @var User $user */
        $user = $guard->user();

        return response()->json([
            'user' => $this->userPayload($user->load('organizerProfile')),
            'token' => $user->createToken('pwa')->plainTextToken,
            'redirect_to' => $this->redirectPath($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        $accessToken = request()->user()?->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } elseif ($bearerToken = request()->bearerToken()) {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        }

        return response()->json(['message' => 'Logout eseguito.']);
    }

    public function me(): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'user' => $this->userPayload($user->load('organizerProfile')),
            'redirect_to' => $this->redirectPath($user),
        ]);
    }

    private function redirectPath(User $user): string
    {
        return match ($user->role) {
            UserRole::Organizer => match ($user->organizerProfile?->verification_status) {
                'verified' => '/organizer/dashboard',
                'pending' => '/organizer/pending',
                'rejected' => '/organizer/rejected',
                'suspended' => '/organizer/suspended',
                default => '/organizer/onboarding',
            },
            UserRole::Admin => '/admin/dashboard',
            default => '/customer/dashboard',
        };
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => $user->status,
            'organizer_profile' => $user->organizerProfile ? [
                'ulid' => $user->organizerProfile->ulid,
                'name' => $user->organizerProfile->name,
                'verification_status' => $user->organizerProfile->verification_status,
                'review_notes' => $user->organizerProfile->review_notes,
            ] : null,
        ];
    }
}
