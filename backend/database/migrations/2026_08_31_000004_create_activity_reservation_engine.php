<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->index();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('public_email')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('booking_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_services', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->boolean('active')->default(true);
            $table->boolean('booking_enabled')->default(true);
            $table->unsignedSmallInteger('min_party_size')->default(1);
            $table->unsignedSmallInteger('max_party_size')->default(10);
            $table->unsignedInteger('minimum_advance_minutes')->default(60);
            $table->unsignedInteger('indicative_duration_minutes')->default(90);
            $table->string('capacity_mode')->default('covers');
            $table->timestamps();
        });

        Schema::create('service_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('activity_services')->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->time('start_time');
            $table->unsignedSmallInteger('capacity');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['service_id', 'weekday', 'start_time']);
        });

        Schema::create('service_availability_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('activity_services')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->jsonb('slots')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'date']);
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained('activity_services')->restrictOnDelete();
            $table->timestamp('starts_at')->index();
            $table->unsignedSmallInteger('party_size');
            $table->string('status')->default('confirmed')->index();
            $table->string('contact_phone', 30);
            $table->text('notes')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'activity_id', 'idempotency_key']);
            $table->index(['service_id', 'starts_at', 'status']);
        });

        Schema::create('activity_followers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['activity_id', 'user_id']);
        });

        Schema::create('activity_reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('would_recommend')->default(false);
            $table->string('status')->default('approved')->index();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['activity_id', 'user_id']);
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('activity_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->after('activity_id')->constrained()->nullOnDelete();
        });
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->foreignId('activity_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->after('activity_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('reservation_id'));
        Schema::table('analytics_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('activity_id'));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('reservation_id'));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('activity_id'));
        Schema::dropIfExists('activity_reviews');
        Schema::dropIfExists('activity_followers');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('service_availability_exceptions');
        Schema::dropIfExists('service_availability_rules');
        Schema::dropIfExists('activity_services');
        Schema::dropIfExists('activities');
    }
};
