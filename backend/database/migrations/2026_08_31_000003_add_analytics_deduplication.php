<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->string('deduplication_key')->nullable()->unique();
        });
        Schema::table('experience_views', function (Blueprint $table): void {
            $table->date('view_date')->nullable();
            $table->unique(['event_id', 'session_id', 'view_date']);
        });
    }

    public function down(): void
    {
        Schema::table('experience_views', function (Blueprint $table): void {
            $table->dropUnique(['event_id', 'session_id', 'view_date']);
            $table->dropColumn('view_date');
        });
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn('deduplication_key');
        });
    }
};
