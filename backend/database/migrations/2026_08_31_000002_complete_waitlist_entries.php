<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->unique();
            $table->jsonb('booking_payload')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->index(['event_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'status', 'created_at']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn(['ulid', 'booking_payload', 'offered_at', 'accepted_at', 'expired_at', 'cancelled_at']);
        });
    }
};
