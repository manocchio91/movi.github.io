<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_subscriptions', fn (Blueprint $table) => $table->unsignedInteger('booking_fee_cents')->nullable());
        Schema::create('organizer_subscription_histories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organizer_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->jsonb('previous_values')->nullable();
            $table->jsonb('new_values');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_subscription_histories');
        Schema::table('organizer_subscriptions', fn (Blueprint $table) => $table->dropColumn('booking_fee_cents'));
    }
};
