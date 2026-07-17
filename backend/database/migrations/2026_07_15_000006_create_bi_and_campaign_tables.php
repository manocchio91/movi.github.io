<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('campaign_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('bookings_generated')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organizer_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('published_events')->default(0);
            $table->unsignedInteger('future_events')->default(0);
            $table->unsignedInteger('completed_events')->default(0);
            $table->unsignedInteger('cancelled_events')->default(0);
            $table->unsignedInteger('sold_out_events')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('participants_total')->default(0);
            $table->unsignedInteger('unique_participants')->default(0);
            $table->unsignedInteger('returning_participants')->default(0);
            $table->decimal('returning_rate', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('followers_total')->default(0);
            $table->unsignedInteger('new_followers')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'period_start', 'period_end']);
        });

        Schema::create('event_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('participants_total')->default(0);
            $table->decimal('fill_rate', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('dashboard_cache', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('cache_key');
            $table->jsonb('payload');
            $table->timestamp('calculated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'cache_key']);
        });

        Schema::create('organizer_opportunity_reports', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('input');
            $table->jsonb('summary');
            $table->jsonb('suggestions');
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_opportunity_reports');
        Schema::dropIfExists('dashboard_cache');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('event_statistics');
        Schema::dropIfExists('organizer_statistics');
        Schema::dropIfExists('campaign_statistics');
        Schema::dropIfExists('campaigns');
    }
};
