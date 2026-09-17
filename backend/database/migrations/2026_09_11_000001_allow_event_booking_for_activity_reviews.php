<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_reviews', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->after('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->change();
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
            $table->dropConstrainedForeignId('booking_id');
            $table->foreignId('reservation_id')->nullable(false)->change();
        });
    }
};
