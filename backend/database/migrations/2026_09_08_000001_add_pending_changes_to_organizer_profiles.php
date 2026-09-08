<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->string('profile_change_status')->nullable()->after('review_notes');
            $table->json('pending_changes')->nullable()->after('profile_change_status');
            $table->ulid('profile_change_request_ulid')->nullable()->unique()->after('pending_changes');
            $table->timestamp('profile_change_submitted_at')->nullable()->after('profile_change_request_ulid');
            $table->foreignId('profile_change_reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('profile_change_submitted_at');
            $table->timestamp('profile_change_reviewed_at')->nullable()->after('profile_change_reviewed_by');
            $table->text('profile_change_review_notes')->nullable()->after('profile_change_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_profiles', function (Blueprint $table): void {
            $table->dropForeign(['profile_change_reviewed_by']);
            $table->dropUnique(['profile_change_request_ulid']);
            $table->dropColumn(['profile_change_status', 'pending_changes', 'profile_change_request_ulid', 'profile_change_submitted_at', 'profile_change_reviewed_by', 'profile_change_reviewed_at', 'profile_change_review_notes']);
        });
    }
};
