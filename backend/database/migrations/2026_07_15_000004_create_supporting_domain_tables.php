<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('organizer_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['organizer_profile_id', 'user_id']);
        });

        Schema::create('user_event_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'category_id']);
        });

        Schema::create('user_location_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('distance_km')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('internal_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('promotional_enabled')->default(false);
            $table->string('frequency')->default('immediate');
            $table->jsonb('preferred_days')->nullable();
            $table->timestamps();
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->constrained();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
        });

        Schema::create('event_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_participants_summary', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('children_age_ranges')->nullable();
            $table->unsignedInteger('adults_total')->default(0);
            $table->unsignedInteger('children_total')->default(0);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('channel');
            $table->string('template');
            $table->string('title');
            $table->text('message');
            $table->string('status')->default('queued')->index();
            $table->boolean('read')->default(false)->index();
            $table->jsonb('payload');
            $table->string('deduplication_key')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('channel');
            $table->string('recipient');
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('mediable');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('reportable');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('media');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('booking_participants_summary');
        Schema::dropIfExists('event_schedules');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['venue_id']);
        });
        Schema::dropIfExists('venues');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_location_preferences');
        Schema::dropIfExists('user_event_preferences');
        Schema::dropIfExists('organizer_members');
        Schema::dropIfExists('user_profiles');
    }
};
