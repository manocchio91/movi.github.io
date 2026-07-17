<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('verification_status')->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable();
            $table->foreignId('municipality_id')->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 280);
            $table->text('description');
            $table->string('status')->default('draft')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('booking_closes_at')->nullable();
            $table->unsignedInteger('capacity_total');
            $table->unsignedInteger('available_total');
            $table->boolean('is_free')->default(true)->index();
            $table->string('price_notes')->nullable();
            $table->boolean('family_friendly')->default(false)->index();
            $table->string('indoor_outdoor')->default('mixed');
            $table->unsignedTinyInteger('minimum_child_age')->nullable();
            $table->unsignedTinyInteger('maximum_child_age')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_categories', function (Blueprint $table): void {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'category_id']);
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('draft','pending_review','published','sold_out','cancelled','completed','rejected','suspended'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_check CHECK (capacity_total >= available_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_categories');
        Schema::dropIfExists('events');
        Schema::dropIfExists('organizer_profiles');
        Schema::dropIfExists('categories');
    }
};
