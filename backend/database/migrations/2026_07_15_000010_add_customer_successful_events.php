<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('event_series_id')->nullable()->after('organizer_profile_id')->constrained('event_series')->nullOnDelete();
            $table->index(['event_series_id', 'starts_at']);
        });

        Schema::create('customer_event_format_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_series_id')->nullable()->constrained('event_series')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organizer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('notify_similar')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_event_format_interests');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['event_series_id']);
            $table->dropIndex(['event_series_id', 'starts_at']);
            $table->dropColumn('event_series_id');
        });

        Schema::dropIfExists('event_series');
    }
};
