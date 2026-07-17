<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_status_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('draft','published','sold_out','cancelled','completed','suspended','archived','pending_review','rejected'))");

        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('published_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable()->after('cancelled_by_user_id');
            $table->text('cancellation_notes')->nullable()->after('cancellation_reason');
            $table->unsignedTinyInteger('admin_creativity_rating')->nullable()->after('show_in_inspiration_feed');
            $table->index(['organizer_profile_id', 'status', 'starts_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('event_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cancelled_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason_code');
            $table->text('reason_text');
            $table->unsignedInteger('bookings_affected')->default(0);
            $table->unsignedInteger('people_affected')->default(0);
            $table->timestamp('cancelled_at');
            $table->timestamps();
        });

        Schema::create('event_rankings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('creativity_score', 5, 2)->default(0);
            $table->decimal('participation_score', 5, 2)->default(0);
            $table->decimal('review_score', 5, 2)->default(0);
            $table->decimal('final_score', 6, 2)->default(0)->index();
            $table->string('ranking_period')->index();
            $table->timestamp('calculated_at');
            $table->string('formula_version')->default('event-ranking-v1');
            $table->timestamps();
        });

        Schema::create('organizer_rankings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('events_created')->default(0)->index();
            $table->unsignedInteger('events_completed')->default(0);
            $table->unsignedInteger('events_cancelled')->default(0);
            $table->unsignedInteger('sold_out_events')->default(0);
            $table->decimal('average_fill_rate', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('total_participants')->default(0);
            $table->unsignedInteger('follower_count')->default(0);
            $table->string('ranking_period')->index();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_rankings');
        Schema::dropIfExists('event_rankings');
        Schema::dropIfExists('event_cancellations');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn(['cancellation_reason', 'cancelled_by_user_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['organizer_profile_id', 'status', 'starts_at']);
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by_user_id',
                'cancellation_reason',
                'cancellation_notes',
                'admin_creativity_rating',
            ]);
        });

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_status_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('draft','pending_review','published','sold_out','cancelled','completed','rejected','suspended'))");
    }
};
