<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\Province;
use App\Models\Region;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
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

        $customer = User::query()->create([
            'name' => 'Cliente Demo',
            'email' => 'customer@example.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Customer,
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

        foreach ([
            ['province_id' => $campobasso->id, 'name' => 'Campobasso', 'istat_code' => '070006'],
            ['province_id' => $campobasso->id, 'name' => 'Termoli', 'istat_code' => '070078'],
            ['province_id' => $campobasso->id, 'name' => 'Bojano', 'istat_code' => '070003'],
            ['province_id' => $isernia->id, 'name' => 'Isernia', 'istat_code' => '094023'],
            ['province_id' => $isernia->id, 'name' => 'Agnone', 'istat_code' => '094002'],
        ] as $municipality) {
            Municipality::query()->create($municipality);
        }

        $categories = collect([
            'bambini',
            'cene ed esperienze gastronomiche',
            'teatro e spettacoli',
            'degustazioni',
            'musica dal vivo',
            'sport',
            'natura ed escursioni',
            'cultura',
            'corsi e laboratori',
            'eventi gratuiti',
            'mercatini e festivita',
        ])->map(fn (string $name) => Category::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]));

        $customer->preferredCategories()->sync(
            $categories->whereIn('name', ['bambini', 'cene ed esperienze gastronomiche'])->pluck('id'),
        );

        NotificationPreference::query()->create([
            'user_id' => $customer->id,
            'internal_enabled' => true,
            'email_enabled' => true,
            'promotional_enabled' => true,
            'frequency' => 'immediate',
        ]);

        $plans = collect([
            [
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'description' => 'Prova configurabile con massimo 2 esperienze attive.',
                'monthly_price_cents' => 0,
                'trial_days' => 30,
                'active_event_limit' => 2,
                'features' => ['2 esperienze attive', 'prenotazioni base', 'profilo pubblico'],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Esperienze illimitate, prenotazioni e statistiche base.',
                'monthly_price_cents' => 1900,
                'trial_days' => null,
                'active_event_limit' => null,
                'features' => ['esperienze illimitate', 'gestione prenotazioni', 'statistiche base'],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Notifiche follower, lista attesa, comunicazioni e visibilita maggiore.',
                'monthly_price_cents' => 4900,
                'trial_days' => null,
                'active_event_limit' => null,
                'features' => ['notifiche follower', 'lista attesa', 'comunicazioni partecipanti', 'statistiche avanzate'],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Predisposto per Comuni, reti di locali e organizzazioni complesse.',
                'monthly_price_cents' => 0,
                'trial_days' => null,
                'active_event_limit' => null,
                'features' => ['multi sede', 'supporto dedicato', 'fatturazione personalizzata'],
            ],
        ])->map(function (array $plan) {
            $features = $plan['features'];
            unset($plan['features']);

            $subscriptionPlan = SubscriptionPlan::query()->create([
                ...$plan,
                'ulid' => (string) Str::ulid(),
                'is_public' => true,
                'is_active' => true,
            ]);

            foreach ($features as $feature) {
                $subscriptionPlan->features()->create([
                    'feature_key' => Str::slug($feature),
                    'name' => $feature,
                ]);
            }

            return $subscriptionPlan;
        });

        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizerUser->id,
            'name' => 'Fattoria Didattica Demo',
            'type' => 'fattoria_didattica',
            'description' => 'Locale demo per eventi famiglia e natura.',
            'municipality_id' => Municipality::query()->where('name', 'Campobasso')->value('id'),
            'address' => 'Contrada Demo 12',
            'email' => 'organizer@example.test',
            'phone' => '+390000000000',
            'website_url' => 'https://fattoria-demo.example',
            'social_links' => ['instagram' => 'https://instagram.com/fattoriademo'],
            'gallery' => ['fattoria-demo-1.jpg', 'fattoria-demo-2.jpg'],
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        OrganizerSubscription::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'subscription_plan_id' => $plans->firstWhere('slug', 'starter')->id,
            'status' => 'active',
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->endOfMonth(),
        ]);

        $customer->followedOrganizers()->syncWithoutDetaching([$profile->id]);

        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => Municipality::query()->where('name', 'Campobasso')->value('id'),
            'title' => 'Laboratorio creativo in fattoria',
            'slug' => 'laboratorio-creativo-in-fattoria',
            'summary' => 'Un pomeriggio per bambini con attivita manuali e merenda inclusa.',
            'description' => 'Esperienza dimostrativa per famiglie, con posti limitati e attivita guidate.',
            'status' => EventStatus::Published,
            'starts_at' => now()->addWeeks(2),
            'booking_closes_at' => now()->addWeeks(2)->subDay(),
            'capacity_total' => 25,
            'available_total' => 22,
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

        $event->categories()->sync($categories->whereIn('name', ['bambini', 'corsi e laboratori'])->pluck('id'));

        $adultTicket = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adulto',
            'description' => 'Ingresso adulto, pagamento sul posto.',
            'audience' => 'adult',
            'max_quantity' => 25,
            'booked_quantity' => 2,
            'price_cents' => 1200,
            'is_active' => true,
        ]);

        $childTicket = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Bambino',
            'description' => 'Ingresso bambino 4-9 anni.',
            'audience' => 'child',
            'max_quantity' => 25,
            'booked_quantity' => 1,
            'price_cents' => 800,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_people' => 3,
            'adults_count' => 2,
            'children_count' => 1,
            'total_price_cents' => 3200,
            'children_age_ranges' => [['range' => '4-7', 'quantity' => 1]],
            'booking_code' => 'EVT-DEMO01',
            'qr_token_hash' => Hash::make(Str::random(48)),
            'booked_at' => now(),
            'event_terms_accepted' => true,
        ]);

        $booking->items()->create([
            'ticket_type_id' => $adultTicket->id,
            'quantity' => 2,
            'unit_price_cents' => 1200,
            'total_price_cents' => 2400,
        ]);

        $booking->items()->create([
            'ticket_type_id' => $childTicket->id,
            'quantity' => 1,
            'unit_price_cents' => 800,
            'total_price_cents' => 800,
        ]);
    }
}
