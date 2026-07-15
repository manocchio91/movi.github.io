<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('audience')->default('adult');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('available')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->unsignedInteger('participants_total');
            $table->unsignedInteger('adults_total')->default(0);
            $table->unsignedInteger('children_total')->default(0);
            $table->jsonb('children_age_ranges')->nullable();
            $table->text('dietary_needs')->nullable();
            $table->text('accessibility_needs')->nullable();
            $table->string('booking_code')->unique();
            $table->string('qr_token_hash')->unique();
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('event_terms_accepted')->default(false);
            $table->timestamps();
        });

        Schema::create('booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('requested_places');
            $table->string('status')->default('waiting')->index();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('scope');
            $table->jsonb('response_payload')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('ticket_types');
    }
};
