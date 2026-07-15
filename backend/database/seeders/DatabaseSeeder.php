<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Molise',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        User::query()->create([
            'name' => 'Partecipante Demo',
            'email' => 'participant@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Participant,
        ]);

        $organizerUser = User::query()->create([
            'name' => 'Organizzatore Demo',
            'email' => 'organizer@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Organizer,
        ]);

        $molise = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $campobasso = Province::query()->create(['region_id' => $molise->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $isernia = Province::query()->create(['region_id' => $molise->id, 'name' => 'Isernia', 'code' => 'IS']);

        $municipalities = [
            ['province_id' => $campobasso->id, 'name' => 'Campobasso', 'istat_code' => '070006'],
            ['province_id' => $campobasso->id, 'name' => 'Termoli', 'istat_code' => '070078'],
            ['province_id' => $campobasso->id, 'name' => 'Bojano', 'istat_code' => '070003'],
            ['province_id' => $isernia->id, 'name' => 'Isernia', 'istat_code' => '094023'],
            ['province_id' => $isernia->id, 'name' => 'Agnone', 'istat_code' => '094002'],
        ];

        foreach ($municipalities as $municipality) {
            Municipality::query()->create($municipality);
        }

        $categories = collect([
            'Eventi per bambini', 'Laboratori creativi', 'Animazione', 'Teatro e spettacoli',
            'Fattorie e natura', 'Sport', 'Eventi gratuiti', 'Pranzi o cene per famiglie',
            'Cene a tema', 'Degustazioni', 'Musica dal vivo', 'Corsi', 'Escursioni',
            'Cultura', 'Mercatini e festività',
        ])->map(fn (string $name) => Category::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]));

        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizerUser->id,
            'name' => 'Fattoria Didattica Demo',
            'type' => 'fattoria_didattica',
            'email' => 'organizer@example.test',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => Municipality::query()->where('name', 'Campobasso')->value('id'),
            'title' => 'Laboratorio creativo in fattoria',
            'slug' => 'laboratorio-creativo-in-fattoria',
            'summary' => 'Un pomeriggio per bambini con attività manuali e merenda inclusa.',
            'description' => 'Esperienza dimostrativa per famiglie, con posti limitati e attività guidate.',
            'status' => EventStatus::Published,
            'starts_at' => now()->addWeeks(2),
            'booking_closes_at' => now()->addWeeks(2)->subDay(),
            'capacity_total' => 25,
            'available_total' => 25,
            'is_free' => false,
            'price_notes' => 'Pagamento sul posto.',
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
            'minimum_child_age' => 4,
            'maximum_child_age' => 9,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'published_at' => now(),
        ]);

        $event->categories()->sync($categories->take(2)->pluck('id'));
    }
}
