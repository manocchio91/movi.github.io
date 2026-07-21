<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private readonly CreateOrganizerProfile $createOrganizerProfile) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = UserRole::from($data['role'] ?? UserRole::Customer->value);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'status' => 'active',
            ]);

            if ($role === UserRole::Organizer) {
                $this->createOrganizerProfile->execute($user, $data);
            }

            return $user->load('organizerProfile');
        });
    }
}
