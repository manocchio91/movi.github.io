<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->plans() as $plan) {
                $features = $plan['features'];
                unset($plan['features']);

                $subscriptionPlan = SubscriptionPlan::query()->updateOrCreate(
                    ['slug' => $plan['slug']],
                    [...$plan, 'ulid' => SubscriptionPlan::query()->where('slug', $plan['slug'])->value('ulid') ?? (string) Str::ulid(), 'is_public' => true, 'is_active' => true],
                );

                foreach ($features as $feature) {
                    $subscriptionPlan->features()->updateOrCreate(
                        ['feature_key' => Str::slug($feature)],
                        ['name' => $feature],
                    );
                }
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function plans(): array
    {
        return [
            ['name' => 'Free Trial', 'slug' => 'free-trial', 'description' => 'Prova configurabile con massimo 2 esperienze attive.', 'monthly_price_cents' => 0, 'trial_days' => 30, 'active_event_limit' => 2, 'features' => ['2 esperienze attive', 'prenotazioni base', 'profilo pubblico']],
            ['name' => 'Starter', 'slug' => 'starter', 'description' => 'Esperienze illimitate, prenotazioni e statistiche base.', 'monthly_price_cents' => 1900, 'trial_days' => null, 'active_event_limit' => null, 'features' => ['esperienze illimitate', 'gestione prenotazioni', 'statistiche base']],
            ['name' => 'Professional', 'slug' => 'professional', 'description' => 'Notifiche follower, lista attesa, comunicazioni e visibilita maggiore.', 'monthly_price_cents' => 4900, 'trial_days' => null, 'active_event_limit' => null, 'features' => ['notifiche follower', 'lista attesa', 'comunicazioni partecipanti', 'statistiche avanzate']],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'description' => 'Predisposto per Comuni, reti di locali e organizzazioni complesse.', 'monthly_price_cents' => 0, 'trial_days' => null, 'active_event_limit' => null, 'features' => ['multi sede', 'supporto dedicato', 'fatturazione personalizzata']],
        ];
    }
}
