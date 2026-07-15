<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->jsonb('gallery')->nullable();
        });

        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->unsignedSmallInteger('active_event_limit')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('external_price_id')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('limits')->nullable();
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'feature_key']);
        });

        Schema::create('organizer_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('trialing')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('external_subscription_id')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('draft')->index();
            $table->string('external_invoice_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organizer_followers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'user_id']);
        });

        Schema::create('experience_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();
            $table->index(['event_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_views');
        Schema::dropIfExists('organizer_followers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('organizer_subscriptions');
        Schema::dropIfExists('subscription_features');
        Schema::dropIfExists('subscription_plans');

        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'cover_path', 'social_links', 'gallery']);
        });
    }
};
