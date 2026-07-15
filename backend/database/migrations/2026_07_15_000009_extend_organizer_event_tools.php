<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->text('program')->nullable()->after('description');
            $table->text('included')->nullable()->after('program');
            $table->text('not_included')->nullable()->after('included');
            $table->text('important_information')->nullable()->after('not_included');
            $table->text('requirements')->nullable()->after('important_information');
            $table->string('recommended_age_range')->nullable()->after('requirements');
            $table->text('equipment_notes')->nullable()->after('recommended_age_range');
            $table->text('allergy_notes')->nullable()->after('equipment_notes');
            $table->text('cancellation_policy')->nullable()->after('allergy_notes');
            $table->string('organizer_contact')->nullable()->after('cancellation_policy');
            $table->boolean('show_in_inspiration_feed')->default(true)->index()->after('published_at');
        });

        Schema::create('ai_event_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('generated_for_month');
            $table->string('generated_for_territory')->nullable();
            $table->jsonb('input_context');
            $table->jsonb('suggestions');
            $table->string('provider')->default('fallback');
            $table->string('model')->nullable();
            $table->string('prompt_version')->default('organizer-opportunities-v1');
            $table->string('status')->default('completed')->index();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'generated_for_month', 'generated_for_territory']);
        });

        Schema::create('event_success_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('fill_rate', 5, 2)->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0);
            $table->decimal('no_show_rate', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->decimal('recommendation_rate', 5, 2)->default(0);
            $table->unsignedInteger('favorite_count')->default(0);
            $table->decimal('success_score', 6, 2)->default(0)->index();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::create('seasonal_event_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('organizer_type')->nullable();
            $table->unsignedTinyInteger('month_from');
            $table->unsignedTinyInteger('month_to');
            $table->string('holiday_code')->nullable();
            $table->string('target')->nullable();
            $table->text('suggested_description')->nullable();
            $table->unsignedInteger('suggested_price_min')->default(0);
            $table->unsignedInteger('suggested_price_max')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_opportunity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('events_count')->default(0);
            $table->string('crowding_level')->default('empty');
            $table->jsonb('public_events_summary')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'date', 'municipality_id', 'province_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_opportunity_snapshots');
        Schema::dropIfExists('seasonal_event_templates');
        Schema::dropIfExists('event_success_scores');
        Schema::dropIfExists('ai_event_suggestions');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'program',
                'included',
                'not_included',
                'important_information',
                'requirements',
                'recommended_age_range',
                'equipment_notes',
                'allergy_notes',
                'cancellation_policy',
                'organizer_contact',
                'show_in_inspiration_feed',
            ]);
        });
    }
};
