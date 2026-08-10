<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StagingBetaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('staging')) {
            throw new RuntimeException('StagingBetaSeeder can only run in staging.');
        }

        $accounts = [
            'admin' => [$this->required('BETA_ADMIN_EMAIL'), $this->password('BETA_ADMIN_PASSWORD'), UserRole::Admin],
            'organizer' => [$this->required('BETA_ORGANIZER_EMAIL'), $this->password('BETA_ORGANIZER_PASSWORD'), UserRole::Organizer],
            'customer' => [$this->required('BETA_CUSTOMER_EMAIL'), $this->password('BETA_CUSTOMER_PASSWORD'), UserRole::Customer],
        ];

        DB::transaction(function () use ($accounts): void {
            foreach (['Cultura', 'Musica dal vivo', 'Sport', 'Natura ed escursioni', 'Eventi gratuiti'] as $name) {
                Category::query()->updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
            }

            $users = [];
            foreach ($accounts as $key => [$email, $password, $role]) {
                $users[$key] = User::query()->updateOrCreate(['email' => $email], [
                    'name' => ucfirst($key).' Beta', 'password' => Hash::make($password),
                    'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
                ]);
            }

            OrganizerProfile::query()->updateOrCreate(['owner_user_id' => $users['organizer']->id], [
                'ulid' => $users['organizer']->organizerProfile?->ulid ?? (string) Str::ulid(),
                'name' => 'Organizer Beta', 'type' => 'associazione', 'email' => $users['organizer']->email,
                'verification_status' => 'verified', 'verified_at' => now(), 'suspended_at' => null,
            ]);
        });
    }

    private function required(string $key): string
    {
        $value = trim((string) env($key));
        if ($value === '') {
            throw new RuntimeException("{$key} is required.");
        }

        return $value;
    }

    private function password(string $key): string
    {
        $value = $this->required($key);
        if (strlen($value) < 12) {
            throw new RuntimeException("{$key} must contain at least 12 characters.");
        }

        return $value;
    }
}
